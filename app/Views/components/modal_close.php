<?php
/**
 * Penutup modal form (pasangan: modal_open).
 *
 * @var string|null $submitLabel
 * @var string|null $show
 */
$submitLabel ??= 'Simpan';
$show        ??= 'modalOpen';
?>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-4 sm:flex-row sm:justify-end">
                    <button type="button" class="btn btn-secondary" @click="<?= esc($show, 'attr') ?> = false">Batal</button>
                    <button type="submit" class="btn btn-primary" :disabled="submitting">
                        <svg x-show="submitting" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/>
                            <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                        <?= esc($submitLabel) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
