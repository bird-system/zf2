<?php

namespace BS\Controller;

// PHPExcel may cause NOTICE error so we have to disable it in order to prevent breaking PHPUnit Test
error_reporting(1);

// set timeout
ini_set('max_execution_time', 60);
use BS\Controller\Exception\AppException;
use Zend\Db\Adapter\Driver\Pdo\Statement;
use Zend\Db\ResultSet\ResultSet;
use Zend\Http\Headers;
use Zend\Http\Response;

abstract class AbstractExporterController extends AbstractRestfulController
{
    /**
     *
     * @var \Zend\Db\Adapter\Adapter
     */
    protected $dbAdapter;

    /**
     *
     * @var [] Column names to be used as header of exported table, also contains SQL query used for that column
     */
    protected $columns = [];

    /**
     *
     * @var \Zend\Db\Sql\Select
     */
    protected $select;

    /**
     *
     * @var string Filename of exported file.
     */
    protected $fileName;

    /**
     *
     * @var ResultSet Array to be exported
     */
    protected $results = null;

    protected $rowBeginPointer = 1;

    protected $onBeforeSheetContentEventName = '';

    protected $onCompleteSheetContentEventName = '';

    const EXPORT_CSV = 'csv';

    const EXPORT_EXCEL = 'excel';

    const EXPORT_HTML = 'html';

    const EXPORT_JSON = 'json';

    protected function _export($isMultipleSheets = false)
    {
        if (null === $this->results) {
            if ($this->select instanceof Statement) {
                $this->results = $this->select->execute();
            }else{
                //TODO:: get a default tableGateway execute $this->select
            }
        }

        $PHPExcel = new \PHPExcel();
        \PHPExcel_Shared_File::setUseUploadTempDirectory(true);
        //PHPExcel creates a default worksheet on construct which we don't need, so delete it
        $PHPExcel->disconnectWorksheets();

        if (!$isMultipleSheets) {
            $this->results = [$this->results];
            $this->columns = [$this->columns];
        }

        $sheetCounter = 0;
        $rowPointer   = 0;
        foreach ($this->results as $title => $sheet) {
            $Worksheet = $PHPExcel->createSheet($sheetCounter);
            $Worksheet->setTitle((string)$title);
            $PHPExcel->setActiveSheetIndex($sheetCounter);

            $this->_beforeSheetContent($PHPExcel, $rowPointer);

            if (count($this->columns[$title]) > 0) {
                $columnPointer = 0;
                $rowPointer    = $this->rowBeginPointer++;
                foreach ($this->columns[$title] as $key => $value) {
                    $headerTitle = $key;
                    if (is_numeric($key)) {
                        $headerTitle = $this->t($value);
                    }
                    $Worksheet->setCellValueByColumnAndRow($columnPointer, $rowPointer, $headerTitle);
                    $columnPointer++;
                }
            }

            foreach ($this->results[$title] as $result) {
                $columnPointer = 0;
                $rowPointer    = $this->rowBeginPointer++;
                foreach ((array)$result as $key => $value) {
                    if (is_array($value)) {
                        $valueList = implode(',', $value);
                        $i = 0;
                        if (strlen($valueList) >= 255) {
                            foreach ($value as $i => $d) {
                                $Worksheet->setCellValue('BA' . ($i + 1), $d);
                            }
                            $Worksheet->getColumnDimension('BA')->setVisible(false);
                            $i++;
                        }
                        $objValidation =
                            $Worksheet->getCellByColumnAndRow($columnPointer, $rowPointer)->getDataValidation();
                        $objValidation->setType(\PHPExcel_Cell_DataValidation::TYPE_LIST);
                        $objValidation->setErrorStyle(\PHPExcel_Cell_DataValidation::STYLE_INFORMATION);
                        $objValidation->setAllowBlank(false);
                        $objValidation->setShowInputMessage(true);
                        $objValidation->setShowErrorMessage(true);
                        $objValidation->setShowDropDown(true);
                        $objValidation->setErrorTitle('Input error');
                        $objValidation->setError('Value is not in list.');
                        if (strlen($valueList) >= 255) {
                            $objValidation->setFormula1("$sheetCounter!BA$1:BA$$i");
                        } else {
                            $objValidation->setFormula1('"' . $valueList . '"');
                        }
                        $value = '';
                    } else {
                        if ((is_numeric($value) && !preg_match('/^0[.]*/', $value) && strlen(trim($value)) < 11) ||
                            (is_float($value * 1) && strlen(trim($value)) < 22 && strpos($value, '.') > 0)
                        ) {
                            $dataType     = \PHPExcel_Cell_DataType::TYPE_NUMERIC;
                            $decimalArray = explode('.', $value);
                            switch (strlen(@$decimalArray[1])) {
                                case '0':
                                    break;
                                case '4':
                                    $Worksheet->getStyleByColumnAndRow($columnPointer, $rowPointer)
                                        ->getNumberFormat()->setFormatCode('0.0000');
                                    break;
                                default:
                                    $Worksheet->getStyleByColumnAndRow($columnPointer, $rowPointer)
                                        ->getNumberFormat()->setFormatCode('0.00');
                            }
                        } else {
                            $dataType = \PHPExcel_Cell_DataType::TYPE_STRING;
                        }
                    }
                    if(isset($dataType))
                        $Worksheet->setCellValueExplicitByColumnAndRow($columnPointer, $rowPointer, $value, $dataType);
                    else
                        $Worksheet->setCellValueExplicitByColumnAndRow($columnPointer, $rowPointer, $value);
                    $columnPointer++;
                }
            }

            $this->_completeSheetContent($PHPExcel, $rowPointer);

            $PHPExcel->removeSheetByIndex($sheetCounter);
            $PHPExcel->addSheet($Worksheet, $sheetCounter);
            $sheetCounter++;
        }

        $Headers  = new Headers();
        $response = new Response();

        if ($this->getParam('toDownload')) {
            $Headers->addHeaderLine('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
            $Headers->addHeaderLine('Cache-Control', 'no-store, no-cache, must-revalidate');
            $Headers->addHeaderLine('Cache-Control', 'post-check=0, pre-check=0, false');
            $Headers->addHeaderLine('Pragma', 'no-cache');


            switch (strtolower($this->getParam('format', self::EXPORT_EXCEL))) {
                case self::EXPORT_EXCEL:
                    $Headers->addHeaderLine('Content-Type',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    $Headers->addHeaderLine('Content-Disposition',
                        'attachment;filename="' . $this->fileName . '.xlsx"');
                    $Writer = new \PHPExcel_Writer_Excel2007($PHPExcel);
                    break;

                case self::EXPORT_CSV:
                    $Headers->addHeaderLine('Content-Type', 'text/csv');
                    $Headers->addHeaderLine('Content-Disposition', 'attachment;filename="' . $this->fileName . '.csv"');
                    $Writer = new \PHPExcel_Writer_CSV($PHPExcel);

                    // @see BS-1770
                    if ('consignmentDetailsFranceCustoms' == $this->getParam('report')) {
                        $Writer->setDelimiter(';');
                        $Writer->setEnclosure('');
                    }

                    // Stupid France system doesn't allow "" to quote columns,
                    // so we have to remove any existing ';'
                    if (';' == $Writer->getDelimiter()) {
                        foreach ($PHPExcel->getAllSheets() as $Sheet) {
                            foreach ($Sheet->getRowIterator() as $Row) {
                                foreach ($Row->getCellIterator() as $Cell) {
                                    /** @var \PHPExcel_Cell $Cell */
                                    $Cell->setValue(str_replace(';', '', $Cell->getValue()));
                                }
                            }
                        }
                    }
                    break;

                default:
                    throw new AppException($this->t('Unkown format requested!'));
            }
            $tempFile = tempnam(sys_get_temp_dir(), 'Export');
            $Writer->save($tempFile);
            $output = file_get_contents($tempFile);
            unlink($tempFile);
            $PHPExcel->disconnectWorksheets();
            unset($Writer, $PHPExcel);
        } else {
            $Writer = new \PHPExcel_Writer_HTML($PHPExcel);
//            $this->view->HTMLWriter = $Writer;
            if ($this->getParam('toPrint')) {
                $headerContentType = 'text/html';
                $output            = $Writer->generateSheetData();
            } else {
                $headerContentType = 'application/json';
                $output            = json_encode(
                    [
                        'success' => true,
                        'html'    => str_replace(PHP_EOL, '',
                            str_replace('	', '', $Writer->generateSheetData())),
                    ]);
            }
            $Headers->addHeaderLine("Content-Type: {$headerContentType}");
        }

        $this->log('Memory usage end => ' . memory_get_usage(true));
        $this->log('Memory peak usage => ' . memory_get_usage(true));

        $response->setHeaders($Headers);
        $response->setContent($output);

        return $response;
    }


    protected function _beforeSheetContent($PHPExcel, $rowPointer)
    {
        $methodName = $this->onBeforeSheetContentEventName;
        if (empty($this->onBeforeSheetContentEventName)) {
            return false;
        }
        if (method_exists($this, $methodName)) {
            $this->$methodName($PHPExcel, $rowPointer);
        }

        return false;
    }


    protected function _completeSheetContent($PHPExcel, $rowPointer)
    {
        $methodName = $this->onCompleteSheetContentEventName;
        if (empty($this->onCompleteSheetContentEventName)) {
            return false;
        }
        if (method_exists($this, $methodName)) {
            $this->$methodName($PHPExcel, $rowPointer);
        }

        return false;
    }


    protected function _getDateGroupMySQLFunction($groupBy, $field, $for = 'column')
    {
        $dateGroupByMySQLFunctions = ['day' => 'DATE', 'month' => 'MONTH', 'year' => 'YEAR'];
        if (!array_key_exists(strtolower($groupBy), $dateGroupByMySQLFunctions)) {
            throw new \Exception($this->t('Undefined date group function!'));
        } else {
            switch (strtolower($groupBy)) {
                case 'month':
                    return 'column' == $for ? "CONCAT(YEAR($field),'-',MONTH($field))" : "YEAR($field),MONTH($field)";
                    break;
                default:
                    return $dateGroupByMySQLFunctions[$groupBy] . "($field)";
                    break;
            }
        }
    }

    protected function _getRecordIds()
    {
        if (!$this->getParam('ids')) {
            throw new AppException($this->t('Required parameter not given!'));
        }

        return explode('_', $this->getParam('ids'));
    }
}