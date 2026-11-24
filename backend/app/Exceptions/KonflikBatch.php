<?php

namespace App\Exceptions;

use RuntimeException;

/** Aksi batch yang bentrok dengan keadaan saat ini (PRD: dibalas 409). */
class KonflikBatch extends RuntimeException {}
