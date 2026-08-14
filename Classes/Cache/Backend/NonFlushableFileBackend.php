<?php
declare(strict_types=1);

namespace WapplerSystems\WsScss\Cache\Backend;

use TYPO3\CMS\Core\Cache\Backend\FileBackend;

/**
 * Registered as the default backend for the 'ws_scss' cache (see
 * ext_localconf.php) in place of plain FileBackend.
 *
 * The 'ws_scss' cache only stores a content hash per compiled file -- a
 * bookkeeping record of "did the .scss source (including its whole @import
 * tree) change since the last compile", not the compiled CSS itself. A
 * blanket `cache:flush` (CLI, deploy hook, Backend "Clear all caches")
 * wipes this cache just like every other one, which forces
 * Compiler::compileFile()/compileSassString() to recompute that whole
 * content hash and, since no cached hash survives to compare against,
 * treat it as changed and recompile from scratch -- on the very next
 * request. Multiplied across every visitor hitting the site concurrently
 * right after a flush, this is a real contributor to slow page renders,
 * even though nothing about the .scss sources actually changed.
 *
 * SCSS output only needs to be recompiled when a .scss source file
 * actually changes, which happens on deploy, not as a side effect of an
 * unrelated cache flush. flush() is therefore a deliberate no-op here;
 * flushByTag()/set()/get()/has() etc. inherited from FileBackend are left
 * untouched, so a targeted flushByTag('scss') (see FlushCacheCommand /
 * FlushCacheController) still invalidates normally.
 */
class NonFlushableFileBackend extends FileBackend
{
    public function flush(): void
    {
        // Intentionally a no-op -- see class docblock.
    }
}
