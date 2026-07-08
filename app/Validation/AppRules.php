<?php

namespace App\Validation;

class AppRules
{
    /**
     * Pastikan tanggal tidak melebihi batas maksimum (format Y-m-d).
     */
    public function date_not_after(
        ?string $str,
        string $maxDate,
        array $data,
        ?string &$error = null,
    ): bool {
        if ($str === null || $str === '') {
            return false;
        }

        $input = strtotime($str);
        $max   = strtotime($maxDate);

        if ($input === false || $max === false) {
            return false;
        }

        if ($input > $max) {
            $error = 'Tanggal tidak boleh melebihi hari ini.';

            return false;
        }

        return true;
    }
}
