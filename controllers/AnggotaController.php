<?php

namespace kanwildiy\controllers;

use docotel\dcms\components\BaseController;
use kanwildiy\modules\pelaporan\components\helper\ViewHelper;
use Yii;
use kanwildiy\models\Anggota;
use kanwildiy\models\AnggotaSearch;
use kanwildiy\components\bll\BllAnggota;
use kanwildiy\components\dal\DalAnggota;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * AnggotaController implements the CRUD actions for Anggota model.
 */
class AnggotaController extends BaseController
{
    public function init()
    {
        $this->layout = "@webroot/themes/lte/layouts/main_pelaporan";
        Yii::$app->errorHandler->errorAction = 'pelaporan/default/error';

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Anggota models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new AnggotaSearch;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }


    public function actionUpdateData()
    {
        DalAnggota::updateDataPegawai();
        return $this->redirect('index');
    }

    /**
     * Displays a single Anggota model.
     * @param string $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->renderAjax('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Anggota model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Anggota();

        if ($model->load(Yii::$app->request->post())) {
            $model->is_pegawai = 0;
            if ($model->save()){
                ViewHelper::setFlash('success','Data berhasil ditambahkan');
                return $this->redirect('index');
            } else {
                ViewHelper::setFlash('danger','Gagal menambahkan data. Error : '.implode(', ',$model->errors));
            }
        }

        return $this->renderAjax('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Anggota model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            if ($model->save()){
                ViewHelper::setFlash('success','Data '.$model->nama.' berhasil diperbarui');
                return $this->redirect('index');
            } else {
                ViewHelper::setFlash('danger','Gagal memperbarui data. Error : '.implode(', ',$model->errors));
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Anggota model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $data = $this->findModel($id);

        if ($data->delete()){
            ViewHelper::setFlash('success','Data '.$data->nama.' berhasil dihapus');
        } else{
            ViewHelper::setFlash('danger','Gagal menghapus data. Error : '.implode(', ',$data->errors));
        }

        return $this->redirect(['index']);
    }

    /**
     * Finds the Anggota model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Anggota the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Anggota::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
