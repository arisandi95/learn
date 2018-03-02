<?php

namespace kanwildiy\controllers;

use Yii;
use kanwildiy\components\Helper;

class PelantikanController extends \docotel\dcms\components\BaseController
{
    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionBaru()
    {
        // return $this->renderPartial('baru');
        $html = $this->renderPartial('baru');
        return Helper::pdf($html, 'Pelantikan-Notaris-Baru', ['margin'=>10]);
    }
    public function actionPengganti()
    {
        // return $this->renderPartial('baru');
        $html = $this->renderPartial('pengganti');
        return Helper::pdf($html, 'Pelantikan-Notaris-Baru', ['margin'=>10]);
    }
}