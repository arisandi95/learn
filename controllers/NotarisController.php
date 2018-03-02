<?php

namespace kanwildiy\controllers;

use Yii;
use kanwildiy\components\RestApi;
use docotel\dcms\components\BaseController;
use kanwildiy\components\bll\BllNotaris;
use kanwildiy\components\bll\BllFidusia;
use kanwildiy\components\Helper;
use kanwildiy\models\Agama;
use kanwildiy\models\Notaris;
use kanwildiy\models\Surat;
use kanwildiy\models\WasiatSearch;
use kanwildiy\models\UserSearch;
use kanwildiy\components\dal\DalNotaris;
use kanwildiy\components\dal\DalJenisWasiat;
use kanwildiy\components\dal\DalFidusia;
use kanwildiy\components\dal\DalWilayah;
use yii\data\ActiveDataProvider;

class NotarisController extends BaseController
{
    public function actionIndex($pensiun = false)
    {
        set_time_limit(3000);
        $post = Yii::$app->request->post();
        if(!sizeof($post)) {
            $post = Yii::$app->request->queryParams;
        }
        if($pensiun) {
            $post['status'] = 4;
        }
        $listNotaris = DalNotaris::listNotaris($post);
        $susunanData = BllNotaris::susunanData($listNotaris);
        return $this->render('index', [
            'listNotaris' => $listNotaris,
            'dataSet' => $susunanData,
            'post' => $post
        ]);
    }

    public function actionDetail($id)
    {
        if (!empty($id)) {
            $detail = RestApi::getDetailNotaris($id);
            if (!empty($detail)) {
                $detail->jenis_kelamin = ($detail->jenis_kelamin == 0) ? 'Laki-laki' : 'Perempuan';
                $detail->id_status_perkawinan = BllNotaris::getPerkawinan($detail->id_status_perkawinan);
                $detail->alamat_kecamatan_id = RestApi::getNamaWilayah($detail->alamat_kecamatan_id);
                $detail->alamat_kabupaten_id = RestApi::getNamaWilayah($detail->alamat_kabupaten_id);
                $detail->alamat_provinsi_id = RestApi::getNamaWilayah($detail->alamat_provinsi_id);
                $detail->pensiun = RestApi::getApi('NotarisPensiun',['tgl_lahir' => $detail->tanggal_lahir]);
                $detail->tanggal_lahir = Helper::formatDateIndonesia($detail->tanggal_lahir);
                if ($detail->tanggal_sertifikat_kodetik && $detail->tanggal_sertifikat_kodetik != '0000-00-00')
                    $detail->tanggal_sertifikat_kodetik = Helper::formatDateIndonesia($detail->tanggal_sertifikat_kodetik);
                if ($detail->tanggal_sk_pelantikan && $detail->tanggal_sk_pelantikan != '0000-00-00')
                    $detail->tanggal_sk_pelantikan = Helper::formatDateIndonesia($detail->tanggal_sk_pelantikan);
                $detail->id_agama = Agama::getAgama($detail->id_agama);
                $detail->nomor_telpon_json = Helper::getTelp($detail->nomor_telpon_json);
                $kantor = null;
                if (!empty($detail->ADDITIONAL)) {
                    $kantor = json_decode($detail->ADDITIONAL);
                    $kantor->provinsi_kantor = RestApi::getNamaWilayah($kantor->provinsi_kantor);
                    $kantor->kabupaten_kantor = RestApi::getNamaWilayah($kantor->kabupaten_kantor);
                    $kantor->kecamatan_kantor = RestApi::getNamaWilayah($kantor->kecamatan_kantor);
                    if (!empty($kantor->tgl_akta_lahir)) {
                        if ($kantor->tgl_akta_lahir !='0000-00-00') {
                            $kantor->tgl_akta_lahir = Helper::formatDateIndonesia($kantor->tgl_akta_lahir);
                        }
                        $kantor->tgl_akta_lahir = '';
                    }
                    $kantor->no_telp_kantor = !empty($kantor->no_telp_kantor) ? $kantor->no_telp_kantor : '-';
                }
                $location = BllNotaris::getLatLong($detail->ADDITIONAL);
                $summary = RestApi::getSummary($detail->id_notaris);
                $status = BllNotaris::getStatusByGroup($detail->group_id);
                $class = BllNotaris::getClassByGroup($detail->group_id);
                return $this->render('detail', [
                    'detail' => $detail,
                    'status' => $status,
                    'class' => $class,
                    'kantor' => $kantor,
                    'summary' => $summary,
                    'location' => $location
                ]);
            }
        }
    }

    public function actionTransaksiPt($id)
    {
        if (!empty($id)) {
            $data = RestApi::getApi('ListTransaksiNotaris', [
                'id_notaris' => $id,
                'tipe_transaksi' => 1
                ]);
            $data = BllNotaris::susunanDataPt($data);
            return $this->render('_pt', [
                'data' => $data,
                ]);
        }
    }

    public function actionTransaksiYayasan($id)
    {
        if (!empty($id)) {
            $data = RestApi::getApi('ListTransaksiNotaris', [
                'id_notaris' => $id,
                'tipe_transaksi' => 2
                ]);
            $data = BllNotaris::susunanDataYayasan($data);
            return $this->render('_yayasan', [
                'data' => $data,
                ]);
        }
    }

    public function actionTransaksiPerkumpulan($id)
    {
        if (!empty($id)) {
            $data = RestApi::getApi('ListTransaksiNotaris', [
                'id_notaris' => $id,
                'tipe_transaksi' => 3
                ]);
            if (!empty($data)) {
                $data = BllNotaris::susunanDataPerkumpulan($data);
                return $this->render('_perkumpulan', [
                    'data' => $data,
                ]);
            }
        }
    }

    public function actionTransaksiWasiat($id)
    {
        if (!empty($id)) {
            $data = RestApi::getApi('ListTransaksiNotaris', [
                'id_notaris' => $id,
                'tipe_transaksi' => 4
                ]);
            $data = BllNotaris::susunanDataWasiat($data);
            return $this->render('_wasiat', [
                'data' => $data,
                ]);
        }
    }

    public function actionDaftarTransaksi()
    {
        if (Yii::$app->request->isAjax) {
            $wilayah = Yii::$app->request->post('wilayah');
            $tipe = Yii::$app->request->post('tipe');
            $status = Yii::$app->request->post('status');
            $data = RestApi::getApi('DaftarTransaksiNotaris',
                [
                    'wilayah' => $wilayah,
                    'id_aksi_transaksi' => $tipe, // Cuti
                    'status_lolos_gagal' => $status //Sudah diverifikasi
                ]);
            if (!empty($data)) {
                $data = BllNotaris::susunanDataTransaksi($data);
                return $this->renderAjax('_table_transaksi',[
                    'data' => $data
                ]);
            } else {
                \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ['status' => 404];
            }
        }
        return $this->render('_daftar_cuti');
    }

    public function actionTransaksiFidusiaWilayah()
    {
        if (Yii::$app->request->isAjax) {
            $wilayah = Yii::$app->request->post('id');
            $tahun = Yii::$app->request->post('tahun');
            $bulan = Yii::$app->request->post('bulan');
            if (!empty($wilayah)) {
                $data = RestApi::getApi('ListFidusiaWilayahNotaris',
                    [
                    'wilayah' => $wilayah,
                    'date' => $tahun.'-'.$bulan
                    ]
                );
                if (!empty($data)) {
                    $data = BllNotaris::susunanDataFidusia($data);
                    return $this->renderAjax('_dataTable',[
                        'data' => $data
                    ]);
                } else {
                    return $this->renderAjax('_empty');
                }
            }
        }
        $tahun = BllNotaris::tahunFidusia();
        $bulan = Helper::listMonth();
        return $this->render('_fidusia', ['tahun' => $tahun, 'bulan' => $bulan]);
    }

    public function actionTransaksiWasiatWilayah()
    {
        if (Yii::$app->request->isAjax) {
            $wilayah = Yii::$app->request->post('id');
            $tahun = Yii::$app->request->post('tahun');
            $bulan = Yii::$app->request->post('bulan');
            $kota = Yii::$app->request->post('kota');
            if (!empty($wilayah)) {
                $data = RestApi::getApi('DaftarWasiatWilayah',
                    [
                        'wilayah' => $wilayah,
                        'date' => $tahun.'-'.$bulan,
                        'kota' => $kota
                    ]);
                if (!empty($data)) {
                    $data = BllNotaris::susunanDataWasiatWilayah($data);
                    return $this->renderAjax('_transaksi_wasiat',[
                        'data' => $data
                    ]);
                } else {
                    return $this->renderAjax('_empty');
                }
            }
        }
        $tahun = BllNotaris::tahunFidusia();
        $bulan = Helper::listMonth();
        $kota = BllNotaris::listKotaJogja();
        return $this->render('_daftar_wasiat', [
            'tahun' => $tahun,
            'bulan' => $bulan,
            'kota' => $kota
            ]);
    }

    public function actionGetDaftarTransaksi()
    {
        if (Yii::$app->request->isAjax) {
            $wilayah = Yii::$app->request->post('wilayah');
            $tipe = Yii::$app->request->post('tipe');
            $status = Yii::$app->request->post('status');
            $id_notaris = Yii::$app->request->post('id_notaris');
            $data = RestApi::getApi('DaftarTransaksiNotaris',
                [
                    'wilayah' => $wilayah,
                    'id_aksi_transaksi' => $tipe, // Cuti
                    'status_lolos_gagal' => $status, //Sudah diverifikasi
                    'id_notaris' => $id_notaris
                ]);
            if (!empty($data)) {
                $data = BllNotaris::susunanDataTransaksi($data);
                return $this->renderAjax('_table_transaksi',[
                    'data' => $data
                ]);
            } else {
                \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ['status' => 404];
            }
        }
    }

    public function actionGetSuratKeputusan()
    {
        if (Yii::$app->request->isAjax) {
            $id_notaris = Yii::$app->request->post('id_notaris');
            $data = RestApi::getApi('SuratKeputusan',
                [
                    'id' => $id_notaris
                ]);
            if (!empty($data)) {
                $data = BllNotaris::susunanDataSurat($data);
                return $this->renderAjax('_table_surat',[
                    'data' => $data
                ]);
            } else {
                return $this->renderAjax('_empty');
            }
        }
    }

    public function actionGetSuratTerkait()
    {
        if (Yii::$app->request->isAjax) {
            if(!($notaris = Notaris::findOne(['username'=>Yii::$app->user->identity->username]))) {
                Yii::info($notaris);
                return $this->renderAjax('_empty');
            }
            $data = Surat::find()->where(['id_notaris'=>$notaris->id_notaris])->orderBy(['tanggal_surat'=>SORT_DESC, 'id_surat'=>SORT_DESC])->all();
            if (!empty($data)) {
                $data = BllNotaris::susunanSuratTerkait($data);
                Yii::warning($data);
                return $this->renderAjax('_table_surat_terkait',[
                    'data' => $data
                ]);
            } else {
                Yii::info($data);
                return $this->renderAjax('_empty');
            }
        }
    }

    public function actionTransaksiPtWilayah()
    {
        if (Yii::$app->request->isAjax) {
            $wilayah = Yii::$app->request->post('wilayah');
            $tahun = Yii::$app->request->post('tahun');
            $bulan = Yii::$app->request->post('bulan');
            if (!empty($wilayah)) {
                $data = RestApi::getApi('TransaksiBakumWilayah',
                    [
                        'wilayah' => $wilayah,
                        'tipe' => 1, //Perseroan
                        'date' => $tahun.'-'.$bulan
                    ]);
                if (!empty($data)) {
                    $data = BllNotaris::susunanDataPtWilayah($data);
                    return $this->renderAjax('_list_pt',[
                        'data' => $data
                    ]);
                } else {
                    return $this->renderAjax('_empty');
                }
            }
        }
        $title = "Daftar Perseroan";
        $link = "/notaris/transaksi-pt-wilayah";
        $tahun = BllNotaris::listTahun();
        $bulan = Helper::listMonth();
        return $this->render('_transaksi_bakum', [
            'judul' => $title,
            'url' => $link,
            'tahun' => $tahun,
            'bulan' => $bulan
            ]);
    }

    public function actionTransaksiYayasanWilayah()
    {
        if (Yii::$app->request->isAjax) {
            $wilayah = Yii::$app->request->post('wilayah');
            $tahun = Yii::$app->request->post('tahun');
            $bulan = Yii::$app->request->post('bulan');
            if (!empty($wilayah)) {
                $data = RestApi::getApi('TransaksiBakumWilayah',
                    [
                        'wilayah' => $wilayah,
                        'tipe' => 2, //Yayasan
                        'date' => $tahun.'-'.$bulan
                    ]);
                if (!empty($data)) {
                    $data = BllNotaris::susunanDataYayasanWilayah($data);
                    return $this->renderAjax('_list_yayasan',[
                        'data' => $data
                    ]);
                } else {
                    return $this->renderAjax('_empty');
                }
            }
        }
        $title = "Daftar Yayasan";
        $link = "/notaris/transaksi-yayasan-wilayah";
        $tahun = BllNotaris::listTahun();
        $bulan = Helper::listMonth();
        return $this->render('_transaksi_bakum', [
            'judul' => $title,
            'url' => $link,
            'tahun' => $tahun,
            'bulan' => $bulan
            ]);
    }

    public function actionTransaksiPerkumpulanWilayah()
    {
        if (Yii::$app->request->isAjax) {
            $wilayah = Yii::$app->request->post('wilayah');
            $tahun = Yii::$app->request->post('tahun');
            $bulan = Yii::$app->request->post('bulan');
            if (!empty($wilayah)) {
                $data = RestApi::getApi('TransaksiBakumWilayah',
                    [
                        'wilayah' => $wilayah,
                        'tipe' => 3, //Perkumpulan
                        'date' => $tahun.'-'.$bulan
                    ]);
                if (!empty($data)) {
                    $data = BllNotaris::susunanDataPerkumpulanWilayah($data);
                    return $this->renderAjax('_list_perkumpulan',[
                        'data' => $data
                    ]);
                } else {
                    return $this->renderAjax('_empty');
                }
            }
        }
        $title = "Daftar Perkumpulan";
        $link = "/notaris/transaksi-perkumpulan-wilayah";
        $tahun = BllNotaris::listTahun();
        $bulan = Helper::listMonth();
        return $this->render('_transaksi_bakum', [
            'judul' => $title,
            'url' => $link,
            'tahun' => $tahun,
            'bulan' => $bulan
            ]);
    }

    public function actionLaporanFidusia()
    {
        $id_wilayah_kanwil = 3603;
        $tahun = date('Y');
        $filter = Yii::$app->request->post('Filter');
        $listWilayah = DalWilayah::getAllProvinsi();
        $rangeYears = Helper::setRangeNumber(2015, date('Y'), 'key', 'val');
        $id = !empty($filter['jenis']) ? $filter['jenis'] : null;
        $tahun = !empty($filter['tahun']) ? $filter['tahun'] : $tahun;
        $wilayah = !empty($filter['wilayah']) ? $filter['wilayah'] : $id_wilayah_kanwil;
        $statistik = BllFidusia::statistikObject($wilayah, $tahun, $id);
        $options = DalFidusia::getMasterObject();
        $tabel = BllFidusia::tabelDataObject($wilayah, $tahun, $id);
        $provider = new ActiveDataProvider([
            'query' => $tabel,
            'pagination' => false,
        ]);
        /*  */
        $statistik2 = BllFidusia::statistik($wilayah, $tahun, $id);
        $options2 = DalFidusia::getMaster();
        $tabel2 = BllFidusia::tabelData($wilayah, $tahun, $id);
        $provider2 = new ActiveDataProvider([
            'query' => $tabel2,
            'pagination' => false,
        ]);

        return $this->render('laporan_fidusia', [
            'listWilayah' => $listWilayah,
            'rangeYears' => $rangeYears,
            'tahun' => $tahun,
            'wilayah' => $wilayah,
            'options' => $options,
            'statistik' => $statistik,
            'id' => $id,
            'provider' => $provider,
            'id_wilayah_kanwil' => $id_wilayah_kanwil,
            'statistik2' => $statistik2,
            'options2' => $options2,
            'tabel2' => $tabel2,
            'provider2' => $provider2
        ]);
    }

    public function actionLaporanWasiat()
    {
        $searchModel = new WasiatSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $reporting = DalJenisWasiat::countAkta(Yii::$app->request->queryParams);
        return $this->render('laporan_wasiat', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'reporting' => $reporting
        ]);
    }

    public function actionCalon()
    {
        return $this->render('calon');
    }

    public function actionListCalon()
    {
        $searchModel = new UserSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        return $this->render('list_calon', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider
        ]);
    }

    public function actionUserStatus()
    {
        $post = Yii::$app->request->Post();
        if (is_numeric($post['status']) && $post['status'] == 10) {
            $update = Yii::$app->db->createCommand()->update('user', ['`status`' => 0], 'id = :id', [':id' => $post['id']]);
            $pesan = 'User berhasil dinon aktifkan.';
        } else {
            $update = Yii::$app->db->createCommand()->update('user', ['`status`' => 10], 'id = :id', [':id' => $post['id']]);
            $pesan = 'User berhasil diaktifkan.';
        }
        if ($update->execute()) {
            Yii::$app->session->setFlash('berhasil', $pesan);
            return $this->redirect(['list-calon']);
        }
    }

    public function actionTransaksiFidusia()
    {
        if (!empty(Yii::$app->request->post('tahun'))) {
            $tahun = Yii::$app->request->post('tahun');
        } else {
            $tahun = date('Y');
        }
        $data = DalFidusia::getRekapitulasi($tahun);
        return $this->render('rekapitulasi_fidusia', ['data' => $data, 'tahun' => $tahun]);
    }

}
