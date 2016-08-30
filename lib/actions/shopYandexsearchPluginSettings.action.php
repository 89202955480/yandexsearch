<?php

class shopYandexsearchPluginSettingsAction extends waViewAction {

    public function execute() {
        $this->view->assign(array(
            'templates' => shopYandexsearchPlugin::$templates,
            'settings' => wa()->getPlugin('yandexsearch')->getSettings(),
            'route_hashs' => shopYandexsearchHelper::getRouteHashs(),
        ));
    }

}
