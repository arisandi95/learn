<?php

namespace kanwildiy\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;
use kanwildiy\models\Konten;
use kanwildiy\models\KontenSearch;
use kanwildiy\models\Uploads;
use kanwildiy\components\Helper;

/**
 * KontenController implements the CRUD actions for Konten model.
 */
class KontenController extends \docotel\dcms\components\BaseController
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
     * Lists all Konten models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new KontenSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Konten model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        if ($model->tipe_konten == 4) {
            $template = $this->renderPartial('_template', ['model' => $model]);
        }
        return $this->render('view', get_defined_vars());
    }

    /**
     * Creates a new Konten model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Konten();
        $post = Yii::$app->request->post();

        if ($model->load($post)) {
            $model->files = UploadedFile::getInstance($model, 'files');

            if ($model->validate()) {

                $path = Yii::$app->basePath.'/web/uploads/konten';
                if(!empty($model->files)) {
                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }
                    $model->files->saveAs($path.'/' . $model->files->baseName . '.' . $model->files->extension);
                }

                if($model->save()) {
                    if(!empty($model->files)) {
                        //simpan ke table files
                        $upload = new Uploads;
                        $upload->id_konten = $model->id_konten;
                        $upload->file = $model->files->baseName . '.' . $model->files->extension;
                        $upload->created_date = date("Y-m-d H:i:s");

                        if($upload->save()) 
                        {
                            return $this->redirect(['index']);
                        } else {
                            //hapus file dan konten
                            unlink($path.'/' . $model->files->baseName . '.' . $model->files->extension);
                            $model->delete();
                            $model->addError('files', 'Gagal Upload File');
                        }
                    }
                    return $this->redirect(['index']);
                }
            }
        }
        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Konten model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $current = NULL;
        $model = $this->findModel($id);
        $post = Yii::$app->request->post();

        if ($model->load($post)) {

            $model->files = UploadedFile::getInstance($model, 'files');
            if ($model->validate()) {

                if($model->save()) {
                    
                    if(!empty($model->files)) {
                        //simpan ke table files
                        $upload = Uploads::find()->where(['id_konten' => $model->id_konten, 'tipe' => NULL])->one();
                        if($upload) {
                            //update dan hapus file
                            $current = $upload->file;
                            $upload->file = $model->files->baseName . '.' . $model->files->extension;
                            $upload->modified_date = date("Y-m-d H:i:s");
                            unlink(Yii::$app->basePath.'/web/uploads/konten/' . $current);
                        } else {
                            $upload = new Uploads;
                            $upload->id_konten = $model->id_konten;
                            $upload->file = $model->files->baseName . '.' . $model->files->extension;
                            $upload->created_date = date("Y-m-d H:i:s");
                        }
                        $model->files->saveAs(Yii::$app->basePath.'/web/uploads/konten/' . $model->files->baseName . '.' . $model->files->extension);

                        if($upload->save())
                        {
                            return $this->redirect(['index']);
                        } else {
                            //hapus file dan konten
                            unlink(Yii::$app->basePath.'/web/uploads/konten/' . $model->files->baseName . '.' . $model->files->extension);
                            $model->addError('files', 'Gagal Upload File');
                        }
                    }
                    return $this->redirect(['index']);
                }
            }
        }
        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Konten model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        if($model = $this->findModel($id))
        {
            //hapus file dan konten
            $upload = Uploads::find()->where(['id_konten' => $model->id_konten, 'tipe' => NULL])->one();
            if($upload) {
                if (file_exists(Yii::$app->basePath.'/web/uploads/konten/' . $upload->file)) {
                    unlink(Yii::$app->basePath.'/web/uploads/konten/' . $upload->file);
                }
                $upload->delete();
            }
            $model->delete();
        }
        return $this->redirect(['index']);
    }

    public function actionDelFiles($id)
    {
        if($model = Uploads::find()->where(['id_konten' => $id])->andWhere(['is', 'tipe', null])->one())
        {
            //hapus file
            if(file_exists(Yii::$app->basePath.'/web/uploads/konten/' . $model->file)) {
                unlink(Yii::$app->basePath.'/web/uploads/konten/' . $model->file);
                $model->delete();
            } else {
                throw new NotFoundHttpException('The file does not exist.');
            }
        }
        return $this->redirect(['update', 'id'=> $id]);
    }

    /**
     * Finds the Konten model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Konten the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Konten::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionDownload($id) 
    {
        if(!empty($id)) {
            if ( ($uploads = Uploads::findOne($id)) !== null ) {
                $path = Yii::getAlias('@app') . '/web/uploads/konten';
                $file = $path . '/' . $uploads->file;
                if (file_exists($file) ) {
                    if (!is_file($file) ) {
                        throw new \yii\web\NotFoundHttpException('The file does not exists.');
                    }
                    return Yii::$app->response->sendFile($file);
                } else {
                    throw new NotFoundHttpException('The file does not exists.');
                }
            } else {
                throw new NotFoundHttpException('The file does not exists.');
            }            
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionSum() {
        $files = ['transaksi_pernotaris_fidusia_total.csv', 'transaksi_pernotaris_bakum_total.csv'];

        $arr_fidusia = $arr_bakum = [];
        foreach ($files as $key => $value) {
            if (($handle = fopen(__DIR__."/".$value, "r")) !== FALSE) {
              while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                $num = count($data);
                if($value == "transaksi_pernotaris_fidusia_total.csv")
                    $arr_fidusia[$data[0]] = array($data[1], (int) $data[2]);
                if($value == "transaksi_pernotaris_bakum_total.csv")
                    $arr_bakum[$data[0]] = array($data[1], (int) $data[2]);
              }
              fclose($handle);
            }
        }

        $tmp = ($arr_fidusia + $arr_bakum);

        foreach ($tmp as $key => $value) {
            $tmp[$key][1] = 0;
            if(!empty($arr_bakum[$key])){
                $tmp[$key][1] = $tmp[$key][1] + $arr_bakum[$key][1];
            }

            if(!empty($arr_fidusia[$key])){
                $tmp[$key][1] = $tmp[$key][1] + $arr_fidusia[$key][1];
            }
        }

        //tulis ke csv
        // output headers so that the file is downloaded rather than displayed
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=akumulasi_fidusia_bakum_id.csv');

        // create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');

        // output the column headings
        fputcsv($output, array('ID NOTARIS', 'NAMA NOTARIS', 'TOTAL'), ';');

        // loop over the rows, outputting them
        foreach ($tmp as $key => $value) {
            fputcsv($output, array($key, $value[0], $value[1]), ';');
        }
    }

    public function actionDownloadPdf($id) 
    {
        if(!empty($id)) {
            if ($model = $this->findModel($id)) {
                $title= $model->judul_konten;
                $title= str_replace(' ', '-', $title);
                $data = Helper::getSurat();
                $konten = $model->getTemplate($model, $data);
                return Helper::pdf($konten, $title, ['margin'=>10]);
            } else {
                throw new NotFoundHttpException('The file does not exists.');
            }
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
