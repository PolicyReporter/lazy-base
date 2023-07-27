<?php

namespace PolicyReporter\LazyBase\Lazy;

class AssociativeArrayObject extends ArrayObject
{
    /**
     * Convert the iterator to an array, maintaining the keys
     */
    public function realize()
    {
        $data = [];
        foreach ($this->iterator as $key => $val) {
            $data[$key] = $val;
        }
        return $data;
    }
}
