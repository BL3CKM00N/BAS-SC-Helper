<?php

namespace App\Observers;

use App\Models\Announcement;
use App\Services\Admin\Audit;

class AnnouncementObserver
{
    public function created(Announcement $announcement): void
    {
        Audit::record('announcement.created', 'Created the announcement "'.$announcement->title.'"', $announcement);
    }

    public function updated(Announcement $announcement): void
    {
        // Marking it as pushed is bookkeeping, not an edit.
        if ($announcement->wasChanged() && array_keys($announcement->getChanges()) !== ['pushed_at', 'updated_at']) {
            Audit::record('announcement.updated', 'Changed the announcement "'.$announcement->title.'"', $announcement);
        }
    }

    public function deleted(Announcement $announcement): void
    {
        Audit::record('announcement.deleted', 'Deleted the announcement "'.$announcement->title.'"', null, ['announcement_id' => $announcement->getKey()]);
    }
}
