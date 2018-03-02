<?php

namespace kanwildiy\controllers;

use Yii;
use yii\helpers\ArrayHelper;
use docotel\dcms\components\BaseController;
use kanwildiy\components\dal\DalProtokol;
use kanwildiy\components\dal\DalNotaris;
use kanwildiy\models\Protokol;
/**
 * ProtokolController implements the CRUD actions for Vcuti model.
 */
class ProtokolController extends BaseController
{

    private $messageService;

    public function __construct($id, $module, $config = [])
    {
        Yii::$container->setSingleton('kanwildiy\components\bll\IMessageService',
            'kanwildiy\components\bll\MessageService');
        $this->messageService = Yii::$container->get('kanwildiy\components\bll\IMessageService');
        parent::__construct($id, $module, $config);
    }

    /**
     * Lists all Vcuti models.
     * @return mixed
     */
    public function actionIndex()
    {
        $dataProvider = DalProtokol::getListProtokol(true);
        return $this->render('index', compact('dataProvider'));
    }

    public function actionBuatBeritaAcara()
    {
        $get = Yii::$app->request->post();
        $model = new Protokol;
        if($get && !isset($get['Protokol']['dokumen'])) {
            $get['Protokol']['dokumen'] = [];
        }
        $model->load($get);
        $model->scenario = "upload";
        if($get && !Yii::$app->request->isAjax) {
            if(DalProtokol::uploadFile($model)) {
                $model->scenario = 'default';
                if($model->save()) {
                   return $this->redirect(['/protokol/index']);
                } else {
                    $model->scenario = "upload";
                    Yii::info($model->errors);
                }
            }
        }
        $listNotaris = ArrayHelper::map(DalNotaris::listNotaris(['status'=>1]), 'id_notaris', 'nama_lengkap');
        return $this->render('_form', compact('model', 'listNotaris'));
    }

    public function actionLihat($id)
    {
        $model = Protokol::findOne($id);
        return $this->render('view', compact('model'));
    }

    public function actionEdit($id)
    {
        $get = Yii::$app->request->post();
        $model = Protokol::findOne($id);
        if($get && !isset($get['Protokol']['dokumen'])) {
            $get['Protokol']['dokumen'] = [];
        }
        $model->load($get);
        $model->scenario = "upload";
        if($get && !Yii::$app->request->isAjax) {
            if(DalProtokol::uploadFile($model)) {
                $model->scenario = 'default';
                if($model->save()) {
                   return $this->redirect(['/protokol/index']);
                } else {
                    $model->scenario = "upload";
                    Yii::info($model->errors);
                }
            }
        }
        $listNotaris = ArrayHelper::map(DalNotaris::listNotaris(['status'=>1]), 'id_notaris', 'nama_lengkap');
        return $this->render('_form', compact('model', 'listNotaris'));
    }

    // public function actionSerahTerima($id)
    // {
    //     $get = Yii::$app->request->post();
    //     $model = Protokol::findOne($id);
    //     $model->scenario = "serah-terima";
    //     if(sizeof($get) > 0){
    //         $model->load($get);
    //         if(DalProtokol::uploadFile($model)) {
    //             $model->scenario = 'default';
    //             if($model->save()) {
    //                return $this->redirect(['/protokol/index']);
    //             }
    //         } else {
    //             Yii::info($model->errors);
    //             Yii::info($model->dokumen);
    //         }    
    //     }
        
    //     return $this->render('serah_terima', compact('model', 'listNotaris'));
    // }

    public function actionDownload($id)
    {
        $model = Protokol::findOne($id);
        $dokumen = json_decode($model->dokumen, true);
        $zipFile = new \ZipArchive();
        $zipPath = "uploads/zipProtokol.zip";
        if ($zipFile->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create a zip file');
        }

        foreach($dokumen as $dok){
            $zipFile->addFile($dok,basename($dok));
            
        }
        $zipFile->close();
        $zipContent = file_get_contents($zipPath);

        unlink($zipPath);
        return Yii::$app->response->sendContentAsFile ( $zipContent, 'dokumen_penyerahan_protokol.zip');
    }
}
