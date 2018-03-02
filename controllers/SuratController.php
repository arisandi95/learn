<?php

namespace kanwildiy\controllers;

use Yii;
use yii\helpers\Url;
use kanwildiy\components\Helper;
use kanwildiy\components\bll\BllSurat;
use kanwildiy\components\dal\DalSurat;
use kanwildiy\models\Surat;
use kanwildiy\models\Konten;
use kanwildiy\models\Notaris;

class SuratController extends \yii\web\Controller
{
    public function actionIndex()
    {
        $dataProvider = DalSurat::search();
        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    } 

    public function actionEdit($id)
    {
        if(($surat = Surat::findOne($id))) {
            $post = Yii::$app->request->post();
            if($post) {
                $konten = [];
                for ($i=0; $i < sizeof($post['key']); $i++) { 
                    $konten[$post['key'][$i]] = $post['value'][$i];
                }
                $surat->konten = json_encode($konten);

                if(!Yii::$app->request->isAjax) {
                    if($surat->save()) {
                        return $this->redirect(['/surat/index']);
                    }
                }
            }
            $listKonten = [];
            preg_match_all("/\#(\w+)\#/", $surat->rKonten->deskripsi_konten, $listKonten);
            $listKonten = array_fill_keys($listKonten[1], "");
            $konten = json_decode($surat->konten, 1);
            $konten = $konten + $listKonten;
            $surat = $surat->id_konten ? $surat->rKonten->getTemplate($surat->rKonten, $konten) : $surat->konten;

            return $this->render('edit', [
                'konten' => is_array($konten) ? $konten : [],
                'surat' => $surat,
            ]);
        }
        return $this->redirect(Yii::$app->request->referrer);
    }

    public function actionView($id)
    {
        set_time_limit(3000);
        if(($surat = Surat::findOne($id)) && !Yii::$app->user->isGuest && $notaris = Notaris::findOne(['username' => Yii::$app->user->identity->username])) {
            if($surat->id_notaris == $notaris->id_notaris) {
                $konten = $surat->rKonten;
                $konten = $surat->id_konten ? $konten->getTemplate($konten, json_decode($surat->konten, 1)) : $surat->konten;
                return Helper::pdf($konten, $surat->judul, ['margin'=>10, 'mode'=>'I']);
            }
        }
        return $this->redirect(Yii::$app->request->referrer);
    }

    public function actionDownload($id, $code = null)
    {
        set_time_limit(3000);
        $surat = Surat::findOne($id);
        if($code && $surat) {
            if(BllSurat::decode($code) == $id) {                
                $konten = $surat->rKonten;
                $konten = $surat->id_konten ? $konten->getTemplate($konten, json_decode($surat->konten, 1)) : $surat->konten;
                return Helper::pdf($konten, $surat->judul, ['margin'=>10]);
            }
        } else if($surat && !Yii::$app->user->isGuest && $notaris = Notaris::findOne(['username'=>Yii::$app->user->identity->username])) {
    		if($surat->id_notaris == $notaris->id_notaris) {                
                $konten = $surat->rKonten;
                $konten = $surat->id_konten ? $konten->getTemplate($konten, json_decode($surat->konten, 1)) : $surat->konten;
        		return Helper::pdf($konten, $surat->judul, ['margin'=>10]);
    		}
    	}
        return $this->redirect(Yii::$app->request->referrer);
    }

    public function actionQrcode($link = null, $code = null) 
    {
        if($link != null && $code === BllSurat::CodeVerifikasi) {
            Yii::$app->response->sendFile(Yii::$app->basePath.'/web/'.$link);
        }
    }

    // public function actionTest() {
    //     $link = Yii::$app->urlManager->createAbsoluteUrl(['/surat/download/',  'id' => 187, 'code'=>
    //         'DVkZaU2JrMHlSalpWYXpsclRVZDRUbFJHVmxKbFJtUlVUVVJHYTFacmEzZFVibU01VUZFOVBRPT0']);
    //     dump(urlencode($link));
    //     exit;
    // }
}
