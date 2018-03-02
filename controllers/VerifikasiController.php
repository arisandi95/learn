<?php

namespace kanwildiy\controllers;

use Yii;
use kanwildiy\models\Vcuti;
use kanwildiy\models\Cuti;
use kanwildiy\models\SearchVcuti;
use kanwildiy\models\CutiSearch;
use docotel\dcms\components\BaseController;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use kanwildiy\components\dal\DalVcuti;
use kanwildiy\components\dal\DalAnggota;
use kanwildiy\components\Helper;
use yii\db\Exception;
use kanwildiy\components\bll\BllNotification;
use kanwildiy\components\bll\BllFirebase;
/**
 * VerifikasiController implements the CRUD actions for Vcuti model.
 */
class VerifikasiController extends BaseController
{

    private $messageService;

    public function __construct($id, $module, $config = [])
    {
        Yii::$container->setSingleton('kanwildiy\components\bll\IMessageService',
            'kanwildiy\components\bll\MessageService');
        $this->messageService = Yii::$container->get('kanwildiy\components\bll\IMessageService');
        parent::__construct($id, $module, $config);
    }

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
     * Lists all Vcuti models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CutiSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Vcuti model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
            'listAnggota' => DalAnggota::getAnggotaAutoComplete(),
        ]);
    }

    /**
     * Deletes an existing Vcuti model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Vcuti model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Vcuti the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Cuti::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionProses()
    {
        $status = Yii::$app->request->post('status');
        $id_cuti = Yii::$app->request->post('id_cuti');
        $id_ketua = Yii::$app->request->post('id_ketua');
        $keterangan = Yii::$app->request->post('keterangan');
        if (!empty($status) && !empty($id_cuti)) {
            $verifikasi = DalVcuti::verifikasi($id_cuti, $status, $id_ketua, $keterangan);
            if ($verifikasi) {
                $status = (int)$status;
                $this->messageService->sendNotifHasilVerifikasi($id_cuti, $status, $keterangan);
                return $this->redirect(['index']);
            }
        }
        return $this->redirect(['view', 'id' => $id_cuti]);
    }

    public function actionSend($id)
    {
        if (!empty($id)) {
            $model = Vcuti::find()->where(['id_cuti' => $id])->one();
            if($model->proses == 1) {
                return $this->redirect(['/transaksi/list-cuti']);
            }
            $id_notaris = Helper::traceOn('tbl_cuti', 'id_notaris', 'id_cuti', $id);
            $group = Helper::traceOn('tbl_verifikasi_cuti', 'id_group', 'id_cuti', $id);
            $model->proses = 1;
            if ($model->save()) {
                $this->messageService->sendNotifikasiVerifikasiCuti($id, $id_notaris, $group);
                BllNotification::NotifikasiVerifikasiCuti($id_notaris, $group, 'group');
                BllFirebase::pushNotifCuti($id_notaris, $group, 'group');
                Yii::$app->session->setFlash('berhasil', "Permohonan Cuti berhasil dikirim ke Petugas MPD/MPW untuk diverifikasi.");
                return $this->redirect(['/transaksi/list-cuti']);
            }
        }
    }
}
