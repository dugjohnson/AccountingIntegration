<?php
session_start();
include "bootstrap.php";

class App extends \Espo\Core\Application
{
    public function setAuth($auth)
    {
        $auth->useNoAuth(true);
        $this->auth = $auth;
    }
}

$app = new App();

if (!$app->isInstalled()) {
    header("Location: install/");
    exit;
}

if (!empty($_GET['entryPoint'])) {
    $app->runEntryPoint($_GET['entryPoint']);
    exit;
}

$app->setAuth(new \Espo\Core\Utils\Auth($app->getContainer()));
//var_dump(get_class_methods(get_class())) ;
//die;
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
    private $uploadStatistics = array();
    private $uploadStatisticsMessage = array(
        "RECORD_REJECTED" => "Number of record rejected",
        "RECORDS_VALIDATED" => "Number of record validated",
        "RECORDS_ALREADY_EXIST" => "Number of record already exist",
        "QB_ACCOUNT_NOT_FOUND"=>"Number of record not found for Quickbook mapping",
        "RECORD_INSERTED" => "Number of record inserted in csv",
        "TOTAL_RECORD" => "Total record found in CSV.");

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
        $this->uploadStatistics = array_fill_keys(array_keys($this->uploadStatisticsMessage),0);
    }
    public function getStleSheet()
    {
        return  "<style type=\"text/css\">
    * {
        margin: 0;
        padding: 0;
    }    
    body {
        font-family: 'Open Sans', sans-serif;
        font-size: 14px;
    }    
    a {
        text-decoration: none;
        padding: 5px;
    }    
    h3 {
        color: red;
        padding: 5px;
    }    
    table {     
        text-align: left;
    }    
    th {
        padding: 8px;
        color: #999;
        font-weight: 400;
    }    
    td {
        padding: 8px;
        border-top: 1px solid #e8eced;
    }    
    form {
        background: #F5F1F1;
        padding: 5px;
    }    
    .btn {
        display: inline-block;
        margin-bottom: 0;
        font-weight: 400;
        text-align: center;
        vertical-align: middle;
        cursor: pointer;
        background-image: none;
        border: 1px solid transparent;
        white-space: nowrap;
        padding: 6px 10px;
        font-size: 14px;
        line-height: 1.36;
    }    
    .btn-success {
        background: #29C37D;
        color: #fff;
    }    
    .btn-warning {
        color: #fff;
        background-color: #ef990e;
        margin: 10px;
    }
    </style>";
    }
    public function run()
    {
        $html = '<html><head>'.$this->getStleSheet().'</head><body>';
        $html .= $this->displayForm();
        if ($this->slim->request->getMethod() == "POST") {
            //$this->slim->redirect("/uploadQB.php",302);
            //$this->slim->response->redirect("uploadQB.php",301);
            $this->doDataUploadTask();
            header("Location:" . $_SERVER['SCRIPT_NAME']);
            return;
        } else if ($this->slim->request->getMethod() == "GET") {
            if (isset($_SESSION['ERROR'])) {
                $html .= "<h2 style='color: red'>Error : " . $_SESSION['ERROR'] . "</h2>";
                unset($_SESSION['ERROR']);
            }
            if (isset($_SESSION['UPLOAD_STATISTICS'])) {
                $table="<table align=\"\" width=\"100%\">";
                $table.="<tr><th>Upload Statistics</th><th>Record</th></tr>";
                foreach($_SESSION['UPLOAD_STATISTICS'] as $key=>$noOfRecord) {
                    $table.= "<tr><td>".$this->uploadStatisticsMessage[$key]."</td><td>$noOfRecord</td></tr>";
                }
                $table.="</table>";
                $html.=$table;
                unset($_SESSION['UPLOAD_STATISTICS']);
            }
        }

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
        $html .= '<input type="submit" name="submit" id="submit" value="Submit" class="btn btn-success" >';
        $html .= '</form>';
        $link = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . '/#Transaction';
        $html .= '<a href="' . $link . '" class="btn btn-warning">Back To Transaction</a>';

        return $html;
    }

    public function mapWithDatabase()
    {
        $entityManager = $this->app->getContainer()->get('entityManager');
        $txnRepository = $entityManager->getRepository('Transaction');
        $accountRepository = $entityManager->getRepository('Account');
        $createdDate = new \DateTime();
        //var_dump(get_class_methods(get_class($entityManager->getUser()))) ;die;

        $activeUser = $this->app->getContainer()->get('user')->id;

        foreach ($this->validateDataArray as $key => $row) {
            $accountObj = $accountRepository->where(array('qbAccount' => $key))->findOne();
            if ($accountObj != null) {
                foreach ($row as $txnRow) {
                    $txnEntity = $txnRepository->where(array(
                        'transNumber' => $txnRow[self::REF_NUMBER_INDEX],
                        'acctNumber' => $txnRow[self::ACCOUNT_NO_INDEX],
                        'txnDescription'=> $txnRow[self::TXN_DESC_INDEX]
                    ))->findOne();

                    if ($txnEntity == null) {
                        $txnEntity = $txnRepository->get();
                        $txnEntity->set('accountId', $accountObj->id);
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
                        $this->uploadStatistics["RECORDS_ALREADY_EXIST"]++;
                    }
                }
            } else {
                $this->uploadStatistics["QB_ACCOUNT_NOT_FOUND"]++;
            }
        }
        $_SESSION['UPLOAD_STATISTICS'] = $this->uploadStatistics;
    }

    public function doCustomValidation($file)
    {
        $validated = false;
        $pathInfo = pathinfo($file['uploadFile']['name']);
        if (strtolower($pathInfo['extension']) != "csv") {
            $_SESSION["ERROR"] = "Please upload only CSV file.";
            //$this->slim->flash("error", "Please upload only CSV file.");
            return $validated;
        }

        if (!move_uploaded_file($file['uploadFile']['tmp_name'], $this->uploadFilePath)) {
            $_SESSION["ERROR"] = "You do not have permission to upload file. please ask your system admin.";
            //$this->slim->flash("error", "You do not have permission to upload file. please ask your system admin.");

            return $validated;
        }

        $fileOpen = fopen($this->uploadFilePath, "r");
        $fileHeader = fgetcsv($fileOpen);

        // validate Header of csv FILE
        if (count($fileHeader) != count($this->validColumnList)) {
            //$this->slim->flash("error", "Please upload valid Quickbook CSV file.");
            $_SESSION["ERROR"] = "Please upload valid Quickbook CSV file.";
            return $validated;

        } else if (md5(var_export($fileHeader, 1)) != md5(var_export($this->validColumnList, 1))) {
            //$this->slim->flash("error", "First row of your csv file should contain below values <br>" . implode('<br>', $this->validColumnList));
            $_SESSION["ERROR"] = "First row of your csv file should contain below values <br>" . implode('<br>', $this->validColumnList);
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
            $this->uploadStatistics["TOTAL_RECORD"]++;

            if ($row[self::ACCOUNT_NO_INDEX] == "") {
                $this->uploadStatistics["RECORD_REJECTED"]++;
                continue;
            } else if ($row[self::PRICE_INDEX] > 0 && $row[self::QTY_INDEX] > 0) {
                $dateTime = new \DateTime();
                $dateTime = $dateTime->createFromFormat('m/d/Y', $row[self::TXN_DATE_INDEX]);
                // check if valid date and time format found or not
                if ($dateTime != null) {
                    $dateTime->setTime(0, 0, 0);
                    $row[self::TXN_DATE_INDEX] = $dateTime->format("Y-m-d H:i:s");
                    $this->uploadStatistics["RECORDS_VALIDATED"]++;
                    $this->validateDataArray[$row[self::ACCOUNT_NO_INDEX]][] = $row;
                } else {
                    $this->uploadStatistics["RECORD_REJECTED"]++;
                }
            } else {
                $this->uploadStatistics["RECORD_REJECTED"]++;
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