<?php

namespace kanwildiy\controllers;

use Yii;
use kanwildiy\models\Buku;
use kanwildiy\models\BukuSearch;
use kanwildiy\models\BukuProtes;
use kanwildiy\models\BukuProtesSearch;
use docotel\dcms\components\BaseController;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use kanwildiy\components\bll\BllBuku;
use kanwildiy\components\dal\DalBuku;
use kanwildiy\components\dal\DalProtes;
use yii\web\User;
use kanwildiy\components\Helper;
/**
 * BukuController implements the CRUD actions for Buku model.
 */
class BukuController extends BaseController
{
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
     * Lists all Buku models.
     * @return mixed
     */
    public function actionIndex($id = null)
    {
        if (empty($id) || $id == 1) {
            return $this->redirect(['merah']);
        } elseif ($id == 2) {
            return $this->redirect(['biru']);
        } else {
            return $this->redirect(['hijau']);
        }
    }

    /**
     * Displays a single Buku model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        $tipe = $model->tipe_buku;
        $title = BllBuku::getCreateTitle($tipe);
        $class = BllBuku::getColorClass($tipe);
        return $this->render('view', get_defined_vars());
    }

    /**
     * Creates a new Buku model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($tipe = null)
    {
        if (empty($tipe)) {
            $tipe = 1;
        }
        $model = new Buku();
        $title = BllBuku::getCreateTitle($tipe);
        $class = BllBuku::getColorClass($tipe);
        $warna = BllBuku::getwarna($tipe);
        $post = Yii::$app->request->post('Buku');
        $get = Yii::$app->request->get('is_nihil');
        $kuasa = null;
        $is_nihil = null;
        if (!empty($get)) {
            $is_nihil = $get;
        }
        if (!empty($post)) {
            if (isset($post['is_nihil']) && $post['is_nihil'] == '2') {
                if ($tipe == 1) {
                    $model->scenario = 'merah_required';
                } else {
                    $model->scenario = 'biju_required';
                }
            }
            $date = explode('-', $post['tanggal_buku']);
            $check = true;
            if(sizeof($date) > 2) {
                $getNihil = DalBuku::cekNihilBulanan($date[0], $date[1], $tipe, $is_nihil == '1' ? null : 1);
                // dump($getNihil);exit;
                if ($getNihil) {
                    $check = false;
                    $textNihil = $is_nihil == '1' ? '' : ' nihil';
                    Yii::$app->session->setFlash('warning', 'sudah ada laporan'.$textNihil.' di bulan '.$date[1]);
                }
            }
            if($check) {
                $save = DalBuku::create($model, $post, $tipe);
                if ($save) {
                    return $this->redirect([$warna]);
                }                
            }
        }
        $getNihil = DalBuku::cekNihilBulanan(date('Y'), date('m'), $tipe);
        return $this->render('create', get_defined_vars());
    }

    /**
     * Updates an existing Buku model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $kuasa = DalBuku::getModelNamaKuasa($id);
        $title = BllBuku::getCreateTitle($model->tipe_buku);
        $class = BllBuku::getColorClass($model->tipe_buku);
        $warna = BllBuku::getwarna($model->tipe_buku);
        $tipe = $model->tipe_buku;
        $get = Yii::$app->request->get('is_nihil');
        $is_nihil = $model->is_nihil ? $model->is_nihil : $get;
        $post = Yii::$app->request->post('Buku');
        if (!empty($post)) {
            $id_kuasa = array();
            if (!empty($kuasa)) {
                foreach ($kuasa as $key => $value) {
                    $id_kuasa[] = $value->id_nama;
                }
            }
            $save = DalBuku::update($model, $post, $model->tipe_buku, $id_kuasa);
            if ($save) {
                return $this->redirect([$warna]);
            }
        }
        $getNihil = DalBuku::cekNihilBulanan(date('Y'), date('m'), $tipe);
        return $this->render('update', get_defined_vars());
    }

    /**
     * Deletes an existing Buku model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $session = Yii::$app->session;
        $model = $this->findModel($id);
        $session->set('tipe_buku', $model->tipe_buku);
        $tipe = $session->get('tipe_buku');
        $this->findModel($id)->delete();
        if ($tipe == 1) {
            return $this->redirect(['merah']);
        } elseif ($tipe == 2) {
            return $this->redirect(['biru']);
        } elseif ($tipe == 3) {
            return $this->redirect(['hijau']);
        } else {
            return $this->redirect(['index']);
        }
    }

    /**
     * Finds the Buku model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Buku the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Buku::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }


    protected function findModelHitam($id)
    {
        if (($model = BukuProtes::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionMerah()
    {
        $user_id = Yii::$app->session->get('user_id');
        $group_id = Yii::$app->session->get('group_id');
        $group = reset($group_id);

        $tahun = !empty(Yii::$app->request->queryParams['BukuSearch']['tahun']) ? Yii::$app->request->queryParams['BukuSearch']['tahun'] : date('Y');
        $bulan = !empty(Yii::$app->request->queryParams['BukuSearch']['bulan']) ? Yii::$app->request->queryParams['BukuSearch']['bulan'] : null;

        $month = Helper::getMonthIndonesia($bulan);
        $title = 'Laporan Buku Daftar Akta Notaris' . ($month ? ' Bulan '.$month : '').' Tahun '.$tahun;

        $searchModel = new BukuSearch();
        $dataProvider = $searchModel->cari(Yii::$app->request->queryParams);

        $tipe = 1;
        $class = BllBuku::getColorClass($tipe);

        $archiveTahun = DalBuku::archiveTahun($tipe);
        $archiveBulan = [];
        foreach ($archiveTahun as $key => $year) {
            $archiveBulan[$year['tahun']] = DalBuku::archiveBulan($year['tahun'], $tipe);
        }
        $warna = BllBuku::getwarna($tipe);
        $getNihil = DalBuku::cekNihilBulanan($tahun, $bulan, $tipe);
        Yii::$app->session['bulan'] = $bulan;

        return $this->render('index', [
            'group' => $group,
            'title' => $title,
            'dataProvider' => $dataProvider,
            'class' => $class,
            'tipe' => $tipe,
            'archiveTahun' => $archiveTahun,
            'archiveBulan' => $archiveBulan,
            'warna' => $warna,
            'tahun' => $tahun,
            'nihil' => $getNihil,
            'searchModel' => $searchModel
        ]);
    }

    public function actionBiru()
    {
        $user_id = Yii::$app->session->get('user_id');
        $group_id = Yii::$app->session->get('group_id');
        $group = reset($group_id);

        $tahun = !empty(Yii::$app->request->queryParams['BukuSearch']['tahun']) ? Yii::$app->request->queryParams['BukuSearch']['tahun'] : date('Y');
        $bulan = !empty(Yii::$app->request->queryParams['BukuSearch']['bulan']) ? Yii::$app->request->queryParams['BukuSearch']['bulan'] : date('n');

        $month = Helper::getMonthIndonesia($bulan);
        $title = 'Laporan Buku Daftar Surat di Bawah Tangan yang Disahkan Notaris Bulan '. $month.' Tahun '.$tahun;

        $searchModel = new BukuSearch();
        $dataProvider = $searchModel->cari(Yii::$app->request->queryParams);

        $tipe = 2;
        $class = BllBuku::getColorClass($tipe);

        $archiveTahun = DalBuku::archiveTahun($tipe);
        $archiveBulan = [];
        foreach ($archiveTahun as $key => $year) {
            $archiveBulan[$year['tahun']] = DalBuku::archiveBulan($year['tahun'], $tipe);
        }
        $warna = BllBuku::getwarna($tipe);
        $getNihil = DalBuku::cekNihilBulanan($tahun, $bulan, $tipe);
        Yii::$app->session['bulan'] = $bulan;

        return $this->render('index', [
            'group' => $group,
            'title' => $title,
            'dataProvider' => $dataProvider,
            'class' => $class,
            'tipe' => $tipe,
            'archiveTahun' => $archiveTahun,
            'archiveBulan' => $archiveBulan,
            'warna' => $warna,
            'tahun' => $tahun,
            'nihil' => $getNihil,
            'searchModel' => $searchModel
        ]);
    }

    public function actionHijau()
    {
        $user_id = Yii::$app->session->get('user_id');
        $group_id = Yii::$app->session->get('group_id');
        $group = reset($group_id);

        $tahun = !empty(Yii::$app->request->queryParams['BukuSearch']['tahun']) ? Yii::$app->request->queryParams['BukuSearch']['tahun'] : date('Y');
        $bulan = !empty(Yii::$app->request->queryParams['BukuSearch']['bulan']) ? Yii::$app->request->queryParams['BukuSearch']['bulan'] : date('n');

        $month = Helper::getMonthIndonesia($bulan);
        $title = 'Laporan Buku Daftar Surat di Bawah Tangan yang Dibukukan Notaris Bulan '. $month.' Tahun '.$tahun;

        $searchModel = new BukuSearch();
        $dataProvider = $searchModel->cari(Yii::$app->request->queryParams);

        $tipe = 3;
        $class = BllBuku::getColorClass($tipe);

        $archiveTahun = DalBuku::archiveTahun($tipe);
        $archiveBulan = [];
        foreach ($archiveTahun as $key => $year) {
            $archiveBulan[$year['tahun']] = DalBuku::archiveBulan($year['tahun'], $tipe);
        }
        $warna = BllBuku::getwarna($tipe);
        $getNihil = DalBuku::cekNihilBulanan($tahun, $bulan, $tipe);
        Yii::$app->session['bulan'] = $bulan;

        return $this->render('index', [
            'group' => $group,
            'title' => $title,
            'dataProvider' => $dataProvider,
            'class' => $class,
            'tipe' => $tipe,
            'archiveTahun' => $archiveTahun,
            'archiveBulan' => $archiveBulan,
            'warna' => $warna,
            'tahun' => $tahun,
            'nihil' => $getNihil,
            'searchModel' => $searchModel
        ]);
    }

    public function actionHitam()
    {
        $user_id = Yii::$app->session->get('user_id');
        $group_id = Yii::$app->session->get('group_id');
        $group = reset($group_id);

        $tahun = !empty(Yii::$app->request->post('tahun')) ? Yii::$app->request->post('tahun') : date('Y');
        $bulan = !empty(Yii::$app->request->post('bulan')) ? Yii::$app->request->post('bulan') : date('m');

        $month = Helper::getMonthIndonesia($bulan);
        $title = 'Laporan Buku Hitam Notaris Bulan '. $month.' Tahun '.$tahun;
        $searchModel = new BukuProtesSearch();
        $dataProvider = $searchModel->cari($tahun, $bulan);

        $archiveTahun = DalProtes::archiveTahun();
        $archiveBulan = [];
        foreach ($archiveTahun as $key => $year) {
            $archiveBulan[$year['tahun']] = DalProtes::archiveBulan($year['tahun']);
        }
        Yii::$app->session['bulan'] = $bulan;
        $getNihil = DalProtes::cekNihilBulanan($tahun, $bulan);

        return $this->render('//hitam/index', get_defined_vars());
    }

    public function actionCreateHitam()
    {
        $model = new BukuProtes();
        $title = 'Laporan Buku Hitam Notaris';
        $post = Yii::$app->request->post('BukuProtes');
        $get = Yii::$app->request->get('is_nihil');
        $tahun = date('Y');
        $bulan = date('m');
        $getNihil = DalProtes::cekNihilBulanan($tahun, $bulan);
        $is_nihil = null;
        if (!empty($get)) {
            $is_nihil = $get;
        }
        if (!empty($post)) {
            if (isset($post['is_nihil']) && $post['is_nihil'] == '2') {
                $model->scenario = '2';
            }
            if ($model->load($post, '')) {
                if ($model->validate()) {
                    if ($model->save()) {
                        return $this->redirect(['hitam']);
                    }
                }
            }
        }
        return $this->render('//hitam/create', get_defined_vars());
    }

    public function actionUpdateHitam($id)
    {
        $model = $this->findModelHitam($id);
        $title = 'Laporan Buku Hitam Notaris';
        $post = Yii::$app->request->post('BukuProtes');
        $get = Yii::$app->request->get('is_nihil');
        $is_nihil = $model->is_nihil ? $model->is_nihil : $get;
        if (!empty($post)) {
            if (isset($post['is_nihil']) && $post['is_nihil'] == '2') {
                $model->scenario = '2';
            }
            if ($model->load($post, '')) {
                if ($model->validate()) {
                    if ($model->save()) {
                        return $this->redirect(['hitam']);
                    }
                }
            }
        }
        return $this->render('//hitam/update', get_defined_vars());
    }

    public function actionViewHitam($id)
    {
        $model = $this->findModelHitam($id);
        $title = 'Laporan Buku Hitam Notaris';
        return $this->render('//hitam/view', get_defined_vars());
    }

    public function actionDeleteHitam($id)
    {
        $this->findModelHitam($id)->delete();

        return $this->redirect(['hitam']);
    }


    public function actionGrafikBuku()
    {
        $year = date('Y');
        $grafik_merah = DalBuku::hitungBulanan(1, $year);
        $grafik_biru  = DalBuku::hitungBulanan(2, $year);
        $grafik_hijau = DalBuku::hitungBulanan(3, $year);
        $grafik_hitam = DalBuku::hitungBulanan(4, $year);
        $grafik_nihil = DalBuku::hitungBulanan(5, $year);
        return $this->render('grafik', get_defined_vars());
    }

    public function actionBukuBiru()
    {
        $user_id = Yii::$app->session->get('user_id');
        $group = Yii::$app->session->get('group_id');
        $group = reset($group);
        $tahun = Yii::$app->request->post('tahun');
        $tahun = !empty($tahun) ? $tahun : date('Y');
        $bulan = Yii::$app->request->post('bulan');
        $bulan = !empty($bulan) ? $bulan : date('m');
        $month = Helper::getMonth($bulan);
        $title = 'Laporan Buku Daftar dii Bawah Tangan Yang Dibukukan Notaris Bulan '. $month;
        $model = new BukuSearch();
        $dataProvider = $model->cari($tahun, $bulan);
        $tipe = 2;
        $class = BllBuku::getColorClass($tipe);
        return $this->render('index', [
            'group' => $group,
            'title' => $title,
            'dataProvider' => $dataProvider,
            'searchModel' => $dataProvider,
            'class' => $class,
            'tipe' => $tipe
        ]);
    }
}
