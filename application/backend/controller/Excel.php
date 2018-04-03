<?php
/**
 * Created by PhpStorm.
 * User: ovo
 * Date: 2017/7/12
 * Time: 下午6:56
 */

namespace app\backend\controller;

class Excel
{
    public function toExcel($title, $content, $first)
    {
        $PHPExcel = new \PHPExcel(); //实例化PHPExcel类，类似于在桌面上新建一个Excel表格
        $PHPSheet = $PHPExcel->getActiveSheet(); //获得当前活动sheet的操作对象
        $PHPSheet->setTitle($title); //给当前活动sheet设置名称
        foreach ($first as $k => $v) {
            $PHPSheet->setCellValue($k, $v);
        }

        foreach ($content as $k => $v) {
            $v = array_values($v);
            foreach ($v as $k1 => $v1) {
                $PHPSheet->setCellValue(chr($k1 + 65) . ($k + 2), $v1);
            }
        }

        // 版本差异信息
        $version_opt = [
            '2007' => [
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ext' => '.xlsx',
                'write_type' => 'Excel2007',
            ],
            '2003' => ['mime' => 'application/vnd.ms-excel',
                'ext' => '.xls',
                'write_type' => 'Excel5',
            ],
            'pdf' => ['mime' => 'application/pdf',
                'ext' => '.pdf',
                'write_type' => 'PDF',
            ],
            'ods' => ['mime' => 'application/vnd.oasis.opendocument.spreadsheet',
                'ext' => '.ods',
                'write_type' => 'OpenDocument',
            ],
        ];

        header('Content-Type: ' . $version_opt["2007"]['mime']);
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $PHPWriter = \PHPExcel_IOFactory::createWriter($PHPExcel, 'Excel2007');//按照指定格式生成Excel文件，'Excel2007'表示生成2007版本的xlsx，'Excel5'表示生成2003版本Excel文件

        $PHPWriter->save('./uploads/file.xlsx');
//        $PHPWriter->save('php://output');
    }

}