<?php

class shopYandexsearchHelper {

    public static function getRouteTemplates($route_hash = null, $template_id = null) {
        if ($route_hash === null) {
            $route_hash = self::getCurrentRouteHash();
        }
        if ($template_id) {
            return self::getRouteTemplate($route_hash, $template_id);
        } else {
            $templates = array();
            foreach (shopYandexsearchPlugin::$templates as $template_id => $template) {
                $templates[$template_id] = self::getRouteTemplate($route_hash, $template_id);
            }
            return $templates;
        }
    }

    protected static function getRouteTemplate($route_hash = null, $template_id) {
        if (empty(shopYandexsearchPlugin::$templates[$template_id])) {
            return false;
        }
        if ($route_hash  === null) {
            $route_hash = self::getCurrentRouteHash();
        }

        $template = shopYandexsearchPlugin::$templates[$template_id];

        $tpl_full_path = $template['tpl_path'] . $route_hash . '.' . $template['tpl_name'] . '.' . $template['tpl_ext'];
        $template_path = wa()->getDataPath($tpl_full_path, $template['public'], 'shop', true);
        if (file_exists($template_path)) {
            $template['template'] = file_get_contents($template_path);
            $template['change_tpl'] = 1;
        } else {
            $tpl_full_path = $template['tpl_path'] . $template['tpl_name'] . '.' . $template['tpl_ext'];
            $template_path = wa()->getAppPath($tpl_full_path, 'shop');
            $template['template'] = file_get_contents($template_path);
            $template['change_tpl'] = 0;
        }
        return $template;
    }

    public static function getRouteSettings($route = null, $setting = null) {
        if ($route === null) {
            $route = self::getCurrentRouteHash();
        }
        $routes = wa()->getPlugin('yandexsearch')->getSettings('routes');
        if (!empty($routes[$route])) {
            $route_settings = $routes[$route];
        } else {
            $route_settings = array();
        }

        if (!$setting) {
            return $route_settings;
        } elseif (!empty($route_settings[$setting])) {
            return $route_settings[$setting];
        } else {
            return null;
        }
    }

    public static function getCurrentRouteHash() {
        $domain = wa()->getRouting()->getDomain(null, true);
        $route = wa()->getRouteUrl('shop/frontend');
        return md5($domain . $route . '*');
    }

    public static function getRouteHashs() {
        $route_hashs = array();
        $routing = wa()->getRouting();
        $domain_routes = $routing->getByApp('shop');
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                $route_url = $domain . '/' . $route['url'];
                $route_hashs[$route_url] = md5($route_url);
            }
        }
        return $route_hashs;
    }

}
