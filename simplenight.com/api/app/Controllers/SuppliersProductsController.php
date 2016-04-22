<?php

use Lib\Event\ProductEvent;
use Symfony\Component\HttpFoundation\Response as SfResponse;
use Simplenight\Core\Representations\ElasticResultRepresentation;
use Simplenight\Core\Representations\SupplierProductRepresentation;
use App\Models\Product\Product;

class SuppliersProductsController extends BaseController
{


    public function __construct()
    {
        parent::__construct();
        $this->beforeFilter('json-schema-validation:product', ['on' => ['post', 'put', 'patch']]);
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function index()
    {
        $data = [];

        $params = Input::all();
        $company_uuid = self::getCompany()->uuid;

        if(isset($params['search'])) {
            $default_sort = '';
        } else {
            $default_sort = '-updated_at';
        }

        list($sorting, $currency, $params) = $this->getParams($default_sort);

        unset($params['currency']);
        $pagination = $this->getPaginationParams();
        $response = [
            'params' => $params,
            'pagination' => [
                'page' => $pagination['params']['page'],
                'per_page' => $pagination['params']['per_page'],
                'total' => 0
            ],
            'data' => []
        ];


        $results = ProductIndex::all($params, $pagination, $sorting, $company_uuid);

        $response['pagination']['total'] = $results['hits']['total'];
        $response['data'] = [];

        if($results['hits']['hits'] !== null && count($results['hits']['hits']) > 0 ) {
            foreach($results['hits']['hits'] as $elastic_array) {
                $representation = new ElasticResultRepresentation($elastic_array);
                $response['data'][] = $representation->toArray('SupplierListItem');
            }
        }
        return $this->respond($response);

    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function show($uuid)
    {
        $product = $this->getProduct($uuid);
        $product = new SupplierProductRepresentation($product);
        $product = ['data' => $product->toArray()];

        return $this->respond($product);
    }

    /**
     * @return mixed
     */
    public function store()
    {
        $json = Request::instance()->getContent();
        $context = new \JMS\Serializer\DeserializationContext();
        $context->setGroups(['Supplier','DefaultFields']);
        /** @var Product $product */
        $product = Serializer::deserialize($json, \App\Models\Product\Product::class, 'json', $context);
        $this->moveInternal($product, null);

        DocumentManager::persist($product);
        DocumentManager::flush();

        Event::fire(
            ProductEvent::TYPE_CREATE,
            new ProductEvent($product, $this->getCompanyId(), $this->getClientUserId(), $this->getClientIPAddress())
        );

        return $this->show($product->getUuid());
    }


    /**
     * @param $uuid
     * @return mixed
     */
    public function update($uuid) {
        $json = Request::instance()->getContent();
        $input = json_decode($json, false);

        $product = $this->getProduct($uuid);

        $new_code = $input->code;
        $old_code = $product->code;

        $p = Serializer::deserialize($json, \App\Models\Product\Product::class, 'json');
        /** @var $p \App\Models\Product\Product */
        $this->moveInternal($p, $product);
        $p->setUuid($uuid);

        DocumentManager::merge($p);
        DocumentManager::flush();

        Event::fire(
            ProductEvent::TYPE_UPDATE,
            new ProductEvent($p, $this->getCompanyId(), $this->getClientUserId(), $this->getClientIPAddress())
        );

        if($new_code != $old_code) {
            InventoryOverride::where('product_code',$old_code)
                ->update(['product_code' => $new_code]);
        }

        return $this->show($uuid);
    }

    /**
     * @return mixed
     */
    public function code()
    {
        $json = Request::instance()->getContent();
        $input = json_decode($json);
        /** @var Product $p */
        $p = \DocumentManager::getRepository(\App\Models\Product\Product::class)->findOneBy([
            'code' => $input->code,
            'company_uuid' => self::getCompany()->uuid,
        ]);

        $product = Serializer::deserialize($json, \App\Models\Product\Product::class, 'json');
        /** @var $product \App\Models\Product\Product */
        $product->setCompanyUuid(self::getCompany()->uuid);
        $product->detectExternal();
        if (!$p)
        {
            $this->moveInternal($product, null);
            DocumentManager::persist($product);
            $this->setStatusCode(SfResponse::HTTP_CREATED);
        }
        else
        {
            $this->moveInternal($product, $p);
            $product->setUuid($p->getUuid());
            DocumentManager::merge($product);
            $this->setStatusCode(SfResponse::HTTP_ACCEPTED);
        }
        DocumentManager::flush();
        $representation = new SupplierProductRepresentation($product);
        $representation = ['data' => $representation->toArray()];

        return $this->respond($representation, [
            'Location' => \URL::route('v1.supplier_products.show', ['uuid' => $product->getUuid() ]),
        ]);
    }

    /**
     * @param Product $product
     * @param Product|null $old_product
     */
    protected function moveInternal(
        \App\Models\Product\Product $product,
        \App\Models\Product\Product $old_product = null)
    {
        $product->setCompanyUuid(self::getCompany()->uuid);
        $product->approval_status = (self::getCompany()->supplier_commission_level == \Company::SUPPLIER_COMMISSION_LEVEL_COMPANY)
            ? Product::APPROVAL_STATUS_ACTIVE
            : ($old_product ? $old_product->approval_status : Product::APPROVAL_STATUS_PENDING);


        if ($old_product){
            $product->setCreatedBy($old_product->getCreatedBy());
            $product->created_at = $old_product->created_at;
        } else {
            $product->created_at = new DateTime();
        }
        $product->updated_at = new DateTime();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function destroy($uuid)
    {
        $product = $this->getProduct($uuid);

        if ($product) {
            Event::fire(
                ProductEvent::TYPE_DELETE,
                new ProductEvent(
                    $product,
                    $this->getCompanyId(),
                    $this->getClientUserId(),
                    $this->getClientIPAddress()
                )
            );
        }

        DocumentManager::remove($product);
        DocumentManager::flush();

        return $this->respondNoContent();
    }

}
