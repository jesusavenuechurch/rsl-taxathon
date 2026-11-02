<?php

namespace App\Domain\Records;

use RuntimeException;

/** The file was rejected; nothing from it was saved. */
class ImportFailed extends RuntimeException {}
