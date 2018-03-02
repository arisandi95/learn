<?php

namespace kanwildiy\controllers;

use Yii;
use yii\base\ErrorException;
use yii\db\Connection;
use yii\db\BaseActiveRecord;
use yii\web\NotFoundHttpException;
use yii\helpers\ArrayHelper;
use kanwildiy\models\Cuti;
use kanwildiy\models\Agama;
use kanwildiy\components\dal\DalCuti;
use kanwildiy\components\dal\DalUser;
use docotel\dcms\components\BaseController;
use kanwildiy\components\bll\BllSurat;
use kanwildiy\components\bll\BllCuti;
use kanwildiy\models\CutiSearch;
use kanwildiy\models\Konten;
use kanwildiy\components\dal\DalVcuti;
use kanwildiy\components\Helper;

class TransaksiController extends BaseController
{

    private $messageService;

    public function __construct($id, $module, $config = [])
    {
        Yii::$container->setSingleton('kanwildiy\components\bll\IMessageService',
            'kanwildiy\components\bll\MessageService');
        $this->messageService = Yii::$container->get('kanwildiy\components\bll\IMessageService');
        parent::__construct($id, $module, $config);
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionDeleteCuti($id)
    {
        if($model = Cuti::findOne($id)) {
            $path = Yii::$app->basePath.'/web/uploads/cuti/';
            $file_ktp = $model->file_ktp;
            $file_ijazah = $model->file_ijazah;
            $file_skck = $model->file_skck;
            $file_sk_sehat = $model->file_sk_sehat;
            $file_sertifikat_cuti = $model->file_sertifikat_cuti;
            if ($model->delete()) {
                if (!empty($file_ktp) && file_exists($path. $file_ktp)) {
                    unlink($path. $file_ktp);
                }
                if (!empty($file_ijazah) && file_exists($path. $file_ijazah)) {
                    unlink($path. $file_ijazah);
                }
                if (!empty($file_skck) && file_exists($path. $file_skck)) {
                    unlink($path. $file_skck);
                }
                if (!empty($file_sk_sehat) && file_exists($path. $file_sk_sehat)) {
                    unlink($path. $file_sk_sehat);
                }
                if (!empty($file_sertifikat_cuti) && file_exists($path. $file_sertifikat_cuti)) {
                    unlink($path. $file_sertifikat_cuti);
                }
            }
        }
        return $this->redirect(['list-cuti']);
    }

    public function actionCreateCuti()
    {
        $tolak = DalCuti::cekCutiDenied();
        $exist = DalCuti::cekCutiExist();
        $lewat = DalCuti::cekLewatMasaCuti();
        $proses = DalCuti::belumProses();
        $session = Yii::$app->session;
        if (!$exist || ($exist && $tolak) || ($exist && $lewat)) {
            if ($proses) {
                Yii::$app->session->setFlash('berhasil', "Anda memiliki permohonan cuti yang belum dikirimkan ke Petugas untuk diverifikasi.");
                return $this->redirect(['list-cuti']);
            }
            $model = new Cuti(['scenario' => 'insert']);
            $post = Yii::$app->request->post('Cuti');
            if (!empty($post)) {
                if ($model->load($post, '')) {
                    $model->file_ktp = BllCuti::saveFileCuti($model, 'file_ktp');
                    $model->file_ijazah = BllCuti::saveFileCuti($model, 'file_ijazah');
                    $model->file_skck = BllCuti::saveFileCuti($model, 'file_skck');
                    $model->file_sk_sehat = BllCuti::saveFileCuti($model, 'file_sk_sehat');
                    $model->file_sertifikat_cuti = BllCuti::saveFileCuti($model, 'file_sertifikat_cuti');
                    $path = Yii::$app->basePath.'/web/uploads/cuti/';
                    if ($model->validate()) {
                        $durasi_cuti = BllCuti::lamaCuti($model->mulai_cuti, $model->akhir_cuti);
                        $session->set('lama_cuti', $durasi_cuti);
                        if ($durasi_cuti <= 12) {
                            if ($model->save()) {
                                $insert_verifikasi = DalVcuti::insertVerifikasi($model->id_cuti);
                                if ($insert_verifikasi) {
                                    Yii::$app->session->setFlash('berhasil', "Permohonan Cuti berhasil dibuat. Silahkan Periksa kembali permohonan Anda, atau dapat langsung dikirim kepetugas untuk diverifikasi.");
                                    return $this->redirect(['list-cuti']);
                                }
                            }
                        } else {
                            Yii::$app->session->setFlash('gagal', "Mohon maaf permohonan Cuti Anda gagal.");
                            unlink($path. $model->file_ktp);
                            unlink($path. $model->file_ijazah);
                            unlink($path. $model->file_skck);
                            unlink($path. $model->file_sk_sehat);
                            unlink($path. $model->file_sertifikat_cuti);
                            return $this->redirect(['create-cuti']);
                        }
                    } else {
                        Yii::$app->session->setFlash('gagal', "Mohon maaf permohonan Cuti Anda gagal.");
                        unlink($path. $model->file_ktp);
                        unlink($path. $model->file_ijazah);
                        unlink($path. $model->file_skck);
                        unlink($path. $model->file_sk_sehat);
                        unlink($path. $model->file_sertifikat_cuti);
                    }
                    
                }
            }
        } else {
            Yii::$app->session->setFlash('berhasil', "Anda telah mengajukan permohonan Cuti, Anda dapat mengajukan cuti kembali setelah melewati masa cuti yang Anda ajukan sebelumnya atau apabila permohonan cuti Anda tidak disetujui.");
            return $this->redirect(['list-cuti']);
        }

        $listAgama = ArrayHelper::map(Agama::find()->all(), 'id', 'agama');

        return $this->render('create', [
            'model' => $model,
            'listAgama' => $listAgama,
        ]);
    }

    public function actionUpdateCuti($id)
    {
        if (!empty($id)) {
            $model = Cuti::findOne($id);
            $model->scenario = 'update';
            $post = Yii::$app->request->post();
            $file_ktp = $model->file_ktp;
            $file_ijazah = $model->file_ijazah;
            $file_skck = $model->file_skck;
            $file_sk_sehat = $model->file_sk_sehat;
            $file_sertifikat_cuti = $model->file_sertifikat_cuti;
            if ($model->load($post)) {
                $ktp = BllCuti::saveFileCuti($model, 'file_ktp');
                $ijazah = BllCuti::saveFileCuti($model, 'file_ijazah');
                $skck = BllCuti::saveFileCuti($model, 'file_skck');
                $sks = BllCuti::saveFileCuti($model, 'file_sk_sehat');
                $sc = BllCuti::saveFileCuti($model, 'file_sertifikat_cuti');
                $model->file_ktp = BllCuti::updateFile($ktp, $file_ktp);
                $model->file_ijazah = BllCuti::updateFile($ijazah, $file_ijazah);
                $model->file_skck = BllCuti::updateFile($skck, $file_skck);
                $model->file_sk_sehat = BllCuti::updateFile($sks, $file_sk_sehat);
                $model->file_sertifikat_cuti = BllCuti::updateFile($sc, $file_sertifikat_cuti);
                if ($model->validate()) {
                    if ($model->save()) {
                        Yii::$app->session->setFlash('berhasil', "Permohonan Cuti berhasil diperbarui.");
                        return $this->redirect(['list-cuti']);
                    }
                    Yii::$app->session->setFlash('gagal', "Mohon maaf permohonan Cuti Anda gagal diperbarui.");
                }
                Yii::$app->session->setFlash('gagal', "Mohon maaf permohonan Cuti Anda gagal diperbarui.");
            }
    
            $listAgama = ArrayHelper::map(Agama::find()->all(), 'id', 'agama');

            return $this->render('update', [
                'model' => $model,
                'listAgama' => $listAgama,
            ]);
        }
    }

    public function actionListCuti()
    {
        $group_id = Yii::$app->session->get('group_id');
        $group = reset($group_id);

        $searchModel = new CutiSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        return $this->render('index', [
            'group' => $group,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider

        ]);
    }


    public function actionDownload($id)
    {
        if (!empty($id)) {
            $path = Yii::getAlias('@app') . '/web/uploads/cuti';
            $file = $path . '/' . $id;
            if (file_exists($file) ) {
                if (!is_file($file) ) {
                    throw new \yii\web\NotFoundHttpException('The file does not exists.');
                }
                return Yii::$app->response->sendFile($file);
            } else {
                throw new NotFoundHttpException('The file does not exists.');
            }
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionViewCuti($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionDownloadSkCuti($id)
    {
        $model = $this->findModel($id);
        if($model && $model->id_sk_cuti != null) {
            return $this->redirect(['/surat/download', 'id' => $model->id_sk_cuti, 'code' => BllSurat::encode($model->id_sk_cuti)]);
        } else {
            return $this->redirect(Yii::$app->request->referrer);
        }
    }

    protected function findModel($id)
    {
        if (($model = Cuti::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }


}
