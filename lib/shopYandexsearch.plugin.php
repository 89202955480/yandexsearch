<?php

class shopYandexsearchPlugin extends shopPlugin {

    public static $templates = array(
        'FrontendSearch' => array(
            'name' => 'Шаблон формы поиска',
            'tpl_path' => 'plugins/yandexsearch/templates/actions/frontend/',
            'tpl_name' => 'FrontendSearch',
            'tpl_ext' => 'html',
            'public' => false
        ),
    );

    public function saveSettings($settings = array()) {
        $route_hash = waRequest::post('route_hash');
        $route_settings = waRequest::post('route_settings');

        if ($routes = $this->getSettings('routes')) {
            $settings['routes'] = $routes;
        } else {
            $settings['routes'] = array();
        }
        $settings['routes'][$route_hash] = $route_settings;
        $settings['route_hash'] = $route_hash;
        parent::saveSettings($settings);

        $templates = waRequest::post('templates');
        foreach ($templates as $template_id => $template) {
            $s_template = self::$templates[$template_id];
            if (!empty($template['reset_tpl'])) {
                $tpl_full_path = $s_template['tpl_path'] . $route_hash . '.' . $s_template['tpl_name'] . '.' . $s_template['tpl_ext'];
                $template_path = wa()->getDataPath($tpl_full_path, $s_template['public'], 'shop', true);
                @unlink($template_path);
            } else {
                $tpl_full_path = $s_template['tpl_path'] . $route_hash . '.' . $s_template['tpl_name'] . '.' . $s_template['tpl_ext'];
                $template_path = wa()->getDataPath($tpl_full_path, $s_template['public'], 'shop', true);
                if (!file_exists($template_path)) {
                    $tpl_full_path = $s_template['tpl_path'] . $s_template['tpl_name'] . '.' . $s_template['tpl_ext'];
                    $template_path = wa()->getAppPath($tpl_full_path, 'shop');
                }
                $content = file_get_contents($template_path);
                if (!empty($template['template']) && $template['template'] != $content) {
                    $tpl_full_path = $s_template['tpl_path'] . $route_hash . '.' . $s_template['tpl_name'] . '.' . $s_template['tpl_ext'];
                    $template_path = wa()->getDataPath($tpl_full_path, $s_template['public'], 'shop', true);
                    $f = fopen($template_path, 'w');
                    if (!$f) {
                        throw new waException('Не удаётся сохранить шаблон. Проверьте права на запись ' . $template_path);
                    }
                    fwrite($f, $template['template']);
                    fclose($f);
                }
            }
        }
    }

    public function frontendSearch() {
        if (!$this->getSettings('status')) {
            return false;
        }

        if (shopYandexsearchHelper::getRouteSettings(null, 'status')) {
            $route_settings = shopYandexsearchHelper::getRouteSettings();
        } elseif (shopYandexsearchHelper::getRouteSettings(0, 'status')) {
            $route_settings = shopYandexsearchHelper::getRouteSettings(0);
        } else {
            return false;
        }

        if (strpos(wa()->getRouting()->getCurrentUrl(), 'search') === false) {
            return false;
        }

        try {
            $html = '';
            if ($route_settings['frontend_search']) {
                $html .= self::form();
            }

            $view = wa()->getView();

            $query = waRequest::get('query');
            if (!$query) {
                throw new waException('Не задан текст поискового запроса');
            }

            $page = waRequest::get('page', 1, 'int');
            if ($page < 1) {
                $page = 1;
            }

            $request = array(
                'apikey' => $route_settings['apikey'],
                'searchid' => $route_settings['searchid'],
                'text' => urlencode($query),
                'page' => $page - 1,
            );
            foreach (array('how', 'price_low', 'price_high', 'category_id', 'available') as $param) {
                if (waRequest::get($param)) {
                    $request[$param] = waRequest::get($param);
                }
            }
            $response = $this->sendRequest('https://catalogapi.site.yandex.net/v1.0', $request);

            if (!empty($response['misspell']['reask']['rule']) && $response['misspell']['reask']['rule'] == 'Misspell') {
                $get = waRequest::get();
                $text = $response['misspell']['reask']['text'];
                $get['query'] = $text;
                $url = wa()->getRouteUrl('/frontend/search') . '?' . http_build_query($get);
                $html .= '<p class="yandexsearch-reask">Показаны результаты по запросу «<a href="' . $url . '">' . $text . '</a>»</p>';
            }

            if (!empty($response['misspell']['reask']['rule']) && $response['misspell']['reask']['rule'] == 'KeyboardLayout') {
                $get = waRequest::get();
                $get['query'] = $response['misspell']['reask']['text'];
                wa()->getResponse()->redirect(wa()->getRouteUrl('/frontend/search') . '?' . http_build_query($get));
                return false;
            }

            if (!empty($response['documents'])) {
                $product_model = new shopProductModel();
                $product_ids = array();
                foreach ($response['documents'] as $item) {
                    $url_parts = explode('/', $item['url']);
                    $product_url = $url_parts[count($url_parts) - 2];
                    if (waRequest::param('category_url')) {
                        $category_model = new shopCategoryModel();
                        $c = $category_model->getByField('full_url', waRequest::param('category_url'));
                        if ($c) {
                            $product = $product_model->getByUrl(waRequest::param('product_url'), $c['id']);
                        }
                    } else {
                        $product = $product_model->getByField('url', $product_url);
                    }
                    if (!empty($product)) {
                        $product_ids[] = $product['id'];
                    }
                }
                if ($product_ids) {
                    $collection = new shopProductsCollection('id/' . implode(',', $product_ids));
                    $count = $response['docsTotal'];
                    $limit = $response['perPage'];
                    $products = $collection->getProducts('*', 0, $limit);
                    $products = $this->sort($products, $product_ids);

                    $pages_count = ceil((float) $count / $limit);

                    $view->assign('pages_count', $pages_count);

                    $view->assign('products', $products);
                    $view->assign('products_count', $count);
                }
            } elseif (!empty($response['errorMessage'])) {
                throw new waException($response['errorMessage']);
            } else {
                $view->assign('products', array());
                $view->assign('products_count', 0);
                $view->assign('pages_count', 0);
            }
        } catch (Exception $ex) {
            $view->assign('products', array());
            $view->assign('products_count', 0);
            $view->assign('pages_count', 0);

            $errors = array(
                'INVALID_KEY' => 'Не верный API-ключ',
                'INVALID_SEARCHID' => 'Не верный SearchID',
            );
            $message = $ex->getMessage();
            if (!empty($errors[$message])) {
                $message = $errors[$message];
            } elseif (preg_match("/Value '(.*)' for key 'searchid' is not valid/", $message, $match)) {
                $message = sprintf("Не верное значение '%s' для ключа 'SearchID'", $match[1]);
            } elseif (preg_match("/Key (.*) not own to search with id (.*)/", $message, $match)) {
                $message = sprintf("Ключ '%s' не пренадлежит поиску с идентификатором %s. Укажите ключ в настройках поиска на Яндекс в разделе «Мои поиски» - «Выдача в JSON»", $match[1], $match[2]);
            }
            waLog::log($message, 'yandexsearch.log');
            $html .= sprintf('<div class="yandexsearch-error"><strong>%s</strong></div>', $message);
        }
        return $html;
    }

    public static function form() {
        $plugin = wa()->getPlugin('yandexsearch');
        if (!$plugin->getSettings('status')) {
            return false;
        }
        $route_hash = null;
        if (shopYandexsearchHelper::getRouteSettings(null, 'status')) {
            $route_hash = null;
            $route_settings = shopYandexsearchHelper::getRouteSettings();
        } elseif (shopYandexsearchHelper::getRouteSettings(0, 'status')) {
            $route_hash = 0;
            $route_settings = shopYandexsearchHelper::getRouteSettings(0);
        } else {
            return false;
        }

        $view = wa()->getView();
        $view->assign('categories', self::getCategories());
        $view->assign('settings', $route_settings);

        $template = shopYandexsearchHelper::getRouteTemplates($route_hash, 'FrontendSearch');

        $html = $view->fetch('string:' . $template['template']);
        return $html;
    }

    protected function sort($products, $product_ids) {
        $sorted_products = array();
        foreach ($product_ids as $product_id) {
            $sorted_products[$product_id] = $products[$product_id];
        }
        return $sorted_products;
    }

    protected function sendRequest($url, $request = null) {
        if (!extension_loaded('curl') || !function_exists('curl_init')) {
            throw new waException('PHP расширение cURL не доступно');
        }

        if (!($ch = curl_init())) {
            throw new waException('curl init error');
        }

        if (curl_errno($ch) != 0) {
            throw new waException('Ошибка инициализации curl: ' . curl_errno($ch));
        }
        $data = array();
        foreach ($request as $param => $value) {
            $data[] = "$param=$value";
        }

        @curl_setopt($ch, CURLOPT_URL, $url . "?" . implode('&', $data));
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //@curl_setopt($ch, CURLOPT_SSLVERSION, 3);
        //@curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


        $response = @curl_exec($ch);
        $app_error = null;
        if (curl_errno($ch) != 0) {
            $app_error = 'Ошибка curl: ' . curl_error($ch);
        }
        $info = curl_getinfo($ch);

        curl_close($ch);
        if ($app_error) {
            throw new waException($app_error);
        }

        if (empty($response)) {
            return false;
        }

        $json = json_decode($response, true);

        return $json;
    }

    protected static function getCategories() {

        $category_model = new shopCategoryModel();
        $route = null;
        $cats = $category_model->getTree(null, null, false, $route);


        $stack = array();
        $result = array();
        foreach ($cats as $c) {
            $c['childs'] = array();

            // Number of stack items
            $l = count($stack);

            // Check if we're dealing with different levels
            while ($l > 0 && $stack[$l - 1]['depth'] >= $c['depth']) {
                array_pop($stack);
                $l--;
            }

            // Stack is empty (we are inspecting the root)
            if ($l == 0) {
                // Assigning the root node
                $i = count($result);
                $result[$i] = $c;
                $stack[] = &$result[$i];
            } else {
                // Add node to parent
                $i = count($stack[$l - 1]['childs']);
                $stack[$l - 1]['childs'][$i] = $c;
                $stack[] = &$stack[$l - 1]['childs'][$i];
            }
        }
        return $result;
    }

}
