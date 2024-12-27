<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;

class FormatDatesInRequest
{
    public function handle($request, Closure $next)
    {
        $data = $request->all();

        foreach ($data as $key => $value) {


            if (is_array($value)) {
                // Recursive call for nested arrays
                $data[$key] = $this->formatDates($value);
            } elseif ($this->isDateFormat($value)) {
                // No need to check the date format again here
                $data[$key] = Carbon::createFromFormat('d-m-Y', $value)->format('Y-m-d');
            }
        }

        $request->merge($data);

        return $next($request);


    }

    private function isDateFormat($value)
    {
        return is_string($value) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $value);
    }
    // private function isDateFormat($value)
    // {
    //     // Check if it's a string and matches the date format pattern
    //     if (is_string($value) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
    //         // Check if it's a valid date
    //         $date = DateTime::createFromFormat('d-m-Y', $value);
    //         return $date && $date->format('d-m-Y') === $value;
    //     }
    //     return false;
    // }
    private function formatDates($array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->formatDates($value);
            } elseif ($this->isDateFormat($value)) {
                // No need to check the date format again here
                $array[$key] = Carbon::createFromFormat('d-m-Y', $value)->format('Y-m-d');
            }
        }

        return $array;
    }





}
