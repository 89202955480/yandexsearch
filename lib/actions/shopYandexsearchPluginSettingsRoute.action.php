<?php

class shopYandexsearchPluginSettingsRouteAction extends waViewAction {

    public function execute() {
        $route_hash = waRequest::get('route_hash');
        $view = wa()->getView();
        $view->assign(array(
            'route_hash' => $route_hash,
            'route_settings' => shopYandexsearchHelper::getRouteSettings($route_hash),
            'templates' => shopYandexsearchHelper::getRouteTemplates($route_hash),
        ));
    }

}
