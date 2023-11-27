<?php
namespace App\Traits ;

trait lfmFillable {
    public function files()
    {
        return $this->morphToMany('Hamahang\LFM\Models\File' , 'fileable','lfm_fileables','fileable_id','file_id')->withPivot('type')->withTimestamps() ;
    }

}
