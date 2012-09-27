<?php

namespace NegativeMargins\Twig;

class MongoDateExtension extends \Twig_Extension_Core
{
    public function getFilters()
    {
        return array_merge(parent::getFilters(), array(
            'date' => new \Twig_Filter_Method($this, 'dateFilter', array('needs_environment' => true)),
        ));
    }

    public function dateFilter(\Twig_Environment $env, $value, $format = null, $timezone = null)
    {
        if ($value instanceof \MongoDate) {
            $date = new \DateTime();
            $date->setTimestamp($value->sec);
        } else {
            $date = $value;
        }

        return twig_date_format_filter($env, $date, $format, $timezone);
    }
}