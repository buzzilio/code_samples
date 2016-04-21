<?php

namespace finance\Models;

/**
 * Domain model
 *
 * @package FinanceNews
 * @author  Alexey Vasilkov
 */

use finance\Log;
use finance\lib\RollingCurl;
use finance\lib\RollingCurlRequest;
use finance\lib\RollingCurlException;

class CheckDomain
{
    /**
     * Store collected domain results
     *
     * @param array $result_list
     */
    protected $result_list                  = [];

    /**
     * Parse domains in Alexa, Google PR, Ahrefs.com
     *
     * @param array $task_list
     * @return array result list
     */
    public function parse($task_list)
    {
        try {

            $curl = new RollingCurl(array($this, 'response'));

            foreach ($task_list as $task) {

                $this->result_list[$task->id] = array(
                    'id'            => $task->id,
                    'alexa_rank'    => null,
                    'google_pr'     => null,
                    'backlinks'     => null,
                    'error'         => ''
                );

                // Alexa.com request
                $curl->add(new RollingCurlRequest(
                    vsprintf('http://www.alexa.com/siteinfo/%s', $task->domain),
                    'GET',
                    null,
                    null,
                    null,
                    array('id' => $task->id, 'type' => 'alexa')
                ));

                // Pagerank request
                $curl->add(new RollingCurlRequest(
                    vsprintf('http://checkpagerank.net/index.php?action=docheck&name=http://%s&submit=Check+PageRank', $task->domain),
                    'GET',
                    null,
                    null,
                    null,
                    array('id' => $task->id, 'type' => 'pagerank')
                ));

                // Ahrefs request
                $curl->add(new RollingCurlRequest(
                    vsprintf('https://ahrefs.com/site-explorer/overview/subdomains/%s', $task->domain),
                    'GET',
                    null,
                    null,
                    null,
                    array('id' => $task->id, 'type' => 'ahrefs')
                ));
            }

            $curl->execute();

        } catch (RollingCurlException $e) {
            Log::add($e->getMessage());
        } catch (\Exception $e) {
            Log::add($e->getMessage());
        }

        return $this->result_list;
    }

    /**
     * Parse response
     *
     * @param string $response
     * @param array $info curl request info array
     * @param \RollingCurlRequest $request
     * @return void
     */
    public function response($response, $info, $request)
    {
        if ($info['http_code'] != 200) {
            Log::add('Wrong response on ' . $request->url);
            $this->addError($request->store['id'], "Wrong response code " . $info['http_code'] . " on " . $request->url);
            return false;
        }

        if (empty($response)) {
            Log::add('Empty response on ' . $request->url);
            $this->addError($request->store['id'], "Empty response on " . $request->url);
            return false;
        }

        try {
            
            switch ($request->store['type']) {
                case 'alexa':

                    $page = new \nokogiri($response);
                    $alexa_rank = $page->get('section#traffic-rank-content div.rank-row span.globleRank strong.metricsUrl a');
                    
                    if (sizeof($found = $alexa_rank->toArray()) && !empty($found[0]['#text']))
                        $this->result_list[$request->store['id']]['alexa_rank'] = (int)str_replace(',', '', $found[0]['#text']);
                    else
                        $this->addError($request->store['id'], "Alexa rank not found");

                    unset($page);
                    
                    break;

                case 'pagerank':

                    $page = new \nokogiri($response);
                    $google_pr = $page->get('div.bodycontentcontainer div.para table.prbox td.prnum b');
                    
                    if (sizeof($found = $google_pr->toArray()) && !empty($found[0]['#text']))
                        $this->result_list[$request->store['id']]['google_pr'] = (int)$found[0]['#text'];
                    else
                        $this->addError($request->store['id'], "Google pr not found");

                    unset($page);

                    break;
                
                case 'ahrefs':

                    $page = new \nokogiri($response);
                    $ahrefs_backlinks = $page->get('div.overview-tables table tr td span');
                    if (sizeof($found = $ahrefs_backlinks->toArray()) && !empty($found[0]['#text']))
                        $this->result_list[$request->store['id']]['backlinks'] = (int)str_replace(',', '', trim($found[0]['#text']));
                    else
                        $this->addError($request->store['id'], "Backlinks not found");

                    unset($page);
                    
                    break;

                default:
                    throw new Exception('Unknown request type');
            }

        } catch (\Exception $e) {
            Log::add('Error response on ' . $request->url . ': ' . $e->getMessage());
            $this->addError($request->store['id'], "Empty response on " . $request->url);
        }   
    }

    /**
     * Add error to history row
     *
     * @param int $id
     * @param string $error
     * @return void
     */
    protected function addError($id, $error)
    {
        if (empty($this->result_list[$id]['error']))
            $this->result_list[$id]['error'] = $error;
        else
            $this->result_list[$id]['error'] .= "\n" . $error;
    }
}