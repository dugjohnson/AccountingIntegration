<?php
/************************************************************************
 * This file is part of Quick Book Accounting System Integration.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2015 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 ************************************************************************/

include "bootstrap.php";

$app = new \Espo\Core\Application();

if (!$app->isInstalled()) {
    header("Location: install/");
    exit;
}

if (!empty($_GET['entryPoint'])) {
    $app->runEntryPoint($_GET['entryPoint']);
    exit;
}

class dataUpload
{
    const ACCOUNT_NO_INDEX = 2;
    const QTY_INDEX = 6;
    const PRICE_INDEX = 11;
    const TXN_DATE_INDEX = 3;
    const DESC_INDEX = 8;
    const REF_NUMBER_INDEX = 4;

    const ITEM_INDEX = 7;
    const MEMO_INDEX = 5;
    const TOTAL_INDEX = 12;
    const TXN_OTHER_INDEX1 = 9;
    const TXN_OTHER_INDEX2 = 10;
    const TXN_DESC_INDEX = 8;

    private $app = null;
    private $slim = null;
    private $uploadFilePath = null;
    private $validateDataArray = null;
    private $uploadStatastics = array();

    public $validColumnList = array("TxnId",
        "Customer",
        "Customer Account Number",
        "TxnDate",
        "RefNumber",
        "Memo",
        "TxnLine Quantity",
        "TxnLine Item",
        "TxnLine Description",
        "TxnLine Other1",
        "TxnLine Other2",
        "TxnLine Cost",
        "TxnLine Amount");

    public function __construct(\Espo\Core\Application $app)
    {
        $this->app = $app;
        $this->slim = $app->getSlim();
        $this->uploadFilePath = __DIR__ . '/data/upload/QB_' . substr(md5(time()), 0, 10) . '.csv';
        $this->uploadStatastics = array("TOTAL_RECORD" => 0, "RECORD_INSERTED" => 0, "RECORD_REJECTED" => 0, "RECORDS_VALIDATED" => 0, "RECORDS_ALREADY_EXIST" => 0);
    }

    public function run()
    {
        $html = '<html><head></head><body>';
        $html .= $this->displayForm();

        if ($this->slim->request->getMethod() == "POST") {
            $this->doDataUploadTask();
        } else if ($this->slim->request->getMethod() == "GET") {
            $html .= "<h2>Error : </h2>";
        }
        var_dump($this->app->getContainer()->get('user'));
        //var_dump(get_class_methods(get_class($this->app->getMetadata())) );
        $html .= '</body></html>';
        echo $html;
    }

    public function doDataUploadTask()
    {
        $file = $_FILES;
        $validated = $this->doCustomValidation($file);
        if ($validated == true) {
            $this->mapWithDatabase();
        }
    }

    public function displayForm()
    {
        $html = '<form action="" method="post" enctype="multipart/form-data"><label>Upload Quickbook Transaction CSV file.</label><input type="file" name="uploadFile" id="uploadFile">';
        $html .= '<input type="submit" name="submit" id="submit" value="Submit" >';
        $html .= '</form>';

        return $html;
    }

    public function mapWithDatabase()
    {
        $entityManager = $this->app->getContainer()->get('entityManager');
        $txnRepository = $entityManager->getRepository('Transaction');
        $createdDate = new \DateTime();

        $activeUser = '555df15a7d1438edf';//$entityManager->getRepository('User')->get(1);

        foreach ($this->validateDataArray as $key => $row) {
            foreach ($row as $txnRow) {

                $txnEntity = $txnRepository->where(array(
                    'transNumber' => $txnRow[self::REF_NUMBER_INDEX],
                    'acctNumber' => $txnRow[self::ACCOUNT_NO_INDEX],
                ))->findOne();
                if ($txnEntity == null) {
                    $txnEntity = $txnRepository->get();
                    $txnEntity->set('accountId', $txnRow[0]);
                    $txnEntity->set('transactionType', 'S');
                    $txnEntity->set('date', $txnRow[self::TXN_DATE_INDEX]);
                    $txnEntity->set('transNumber', $txnRow[self::REF_NUMBER_INDEX]);
                    $txnEntity->set('acctNumber', $txnRow[self::ACCOUNT_NO_INDEX]);
                    $txnEntity->set('item', $txnRow[self::ITEM_INDEX]);
                    $txnEntity->set('memo', $txnRow[self::MEMO_INDEX]);
                    $txnEntity->set('qty', $txnRow[self::QTY_INDEX]);
                    $txnEntity->set('price', $txnRow[self::PRICE_INDEX]);
                    $txnEntity->set('total', $txnRow[self::TOTAL_INDEX]);
                    $txnEntity->set('createdAt', $createdDate);
                    $txnEntity->set('modifiedAt', $createdDate);
                    ///need to set current user id
                    $txnEntity->set('createdById', $activeUser);
                    $txnEntity->set('modifiedById', $activeUser);
                    $txnEntity->set('assignedUserId', $activeUser);

                    $txnEntity->set('txnOther1', $txnRow[self::TXN_OTHER_INDEX1]);
                    $txnEntity->set('txnOther2', $txnRow[self::TXN_OTHER_INDEX2]);
                    $txnEntity->set('txnDescription', $txnRow[self::TXN_DESC_INDEX]);

                    $entityManager->saveEntity($txnEntity);
                } else {
                    $this->uploadStatastics["RECORDS_ALREADY_EXIST"]++;
                }
            }
        }

    }

    public function doCustomValidation($file)
    {
        $validated = false;
        $pathInfo = pathinfo($file['uploadFile']['name']);

        if ($file['uploadFile']['type'] != 'text/csv' || strtolower($pathInfo['extension']) != "csv") {
            $this->slim->flash("error", "Please upload only CSV file.");
            return $validated;
        }

        if (!move_uploaded_file($file['uploadFile']['tmp_name'], $this->uploadFilePath)) {
            $this->slim->flash("error", "You do not have permission to upload file. please ask your system admin.");

            return $validated;
        }

        $fileOpen = fopen($this->uploadFilePath, "r");
        $fileHeader = fgetcsv($fileOpen);

        // validate Header of csv FILE
        if (count($fileHeader) != count($this->validColumnList)) {
            $this->slim->flash("error", "Please upload valid Quickbook CSV file.");
            return $validated;

        } else if (md5(var_export($fileHeader, 1)) != md5(var_export($this->validColumnList, 1))) {
            $this->slim->flash("error", "First row of your csv file should contain below values <br>" . implode('<br>', $this->validColumnList));
            return $validated;
        }
        /*
        $accountNoColumnIndex = 2;
        $quantityIndex = 6;
        $priceIndex = 11;
        $txnDateIndex = 3;
        $descriptionIndex = 8;
        $refNumber = 4; */

        while ($row = fgetcsv($fileOpen)) {
            $this->uploadStatastics["TOTAL_RECORD"]++;

            if ($row[self::ACCOUNT_NO_INDEX] == "") {
                $this->uploadStatastics["RECORD_REJECTED"]++;
                continue;
            } else if ($row[self::PRICE_INDEX] > 0 && $row[self::QTY_INDEX] > 0) {
                $dateTime = new \DateTime();
                $dateTime = $dateTime->createFromFormat('m/d/Y', $row[self::TXN_DATE_INDEX]);
                // check if valid date and time format found or not
                if ($dateTime != null) {
                    $dateTime->setTime(0, 0, 0);
                    $row[self::TXN_DATE_INDEX] = $dateTime->format("Y-m-d H:i:s");
                    $this->uploadStatastics["RECORDS_VALIDATED"]++;
                    $this->validateDataArray[$row[self::ACCOUNT_NO_INDEX]][] = $row;
                } else {
                    $this->uploadStatastics["RECORD_REJECTED"]++;
                }
            } else {
                $this->uploadStatastics["RECORD_REJECTED"]++;
            }
        }

        fclose($fileOpen);
        $validated = true;

        return $validated;
    }
}

//////////////////code execution///////////////////////////////
$dataUpload = new dataUpload($app);
$dataUpload->run();