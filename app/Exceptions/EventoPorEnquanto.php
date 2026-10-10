<?php

namespace App\Exceptions;

/** Evento do POS offline que ainda nao pode ser registado, mas pode ser no proximo envio. */
class EventoPorEnquanto extends \RuntimeException
{
}
