<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class ReviewExcelExport implements FromCollection, WithHeadings, WithEvents {

    protected $exportData;
    
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($reviewList)
    {
        $this->exportData = $reviewList;
    }
    
    // set the headings
    public function headings():array
    {
        return ['Customer Name', 'Facility Name', 'Rating', 'Review', 'Submitted On'];
    }

    // freeze the first row with headings
    public function registerEvents():array
    {
        return [            
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->freezePane('A2', 'A2');
            },
        ];
    }

    public function collection()
    {        
        // ..but I return a collection from the built array data
        return collect($this->exportData);
    }
}
