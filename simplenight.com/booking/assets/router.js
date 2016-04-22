(function($app)
{
    "use strict";

    $app.module('page.results.router', function ()
    {
        var go = function (url, silent) {
            routie.navigate('/filter/' + url, silent ? {silent: true} : silent);
        };

        var deserialize = function (string) {

            if (!_.isString(string)) {
                return [];
            }

            var params = [];

            _.each(string.split(";"), function (part) {
                part = part.split(':');
                if (2 !== part.length) {
                    return ;
                }
                params.push({
                    name: part[0],
                    values: part[1].split(',')
                });
            });

            return params;
        };

        var init = function () {

            routie('/filter/:params?', function(params)
            {
                params = deserialize(params);

                sn_module('page.results.view').apply(params);
                sn_module('page.results.filters').apply(params);
                sn_module('page.results.refine').apply(params);
                sn_module('page.results.sort').apply(params);

                sn_module('page.results.service').request();
            });
        };

        return {
            element: '#items-list',
            priority: $app.mediumPriority(),
            init: init,
            go: go,
            deserialize: deserialize,
            update: function () {
                var params = _.compact([
                    sn_module('page.results.view').toUrl(),
                    sn_module('page.results.filters').toUrl(),
                    sn_module('page.results.sort').toUrl(),
                    sn_module('page.results.refine').toUrl()
                ]);

                go(params.join(';'));
            }
        };
    });

})(simplenight.$app);