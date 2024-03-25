<?php

namespace Verseles\SevenZip\Exceptions;

use Throwable;

class ExecutableNotFoundException extends \Exception
{

  public function __construct($message = 'Executable 7z not found.', $code = 0, Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }

}
