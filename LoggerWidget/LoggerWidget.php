<?php

namespace sazik\elastic\LoggerWidget;

class LoggerWidget extends \yii\base\Widget {

    private $log = [];

    public function run() {
        parent::run();
        if (\Yii::$app->elastic->logEnabled()) {
            $this->log = \Yii::$app->elastic->getLog();
            return $this->render("view", ["log" => $this->log]);
        }
        return '';
    }

}
