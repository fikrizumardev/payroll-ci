<?php
/**
 * Badge status berbentuk pill.
 *
 * @var string      $status kunci status (draft, paid, active, late, …) — lihat status_meta()
 * @var string|null $label  override label
 */
echo status_badge($status, $label ?? null);
