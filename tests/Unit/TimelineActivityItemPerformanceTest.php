<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TimelineActivityItemPerformanceTest extends TestCase
{
    public function test_activity_details_dialog_defers_expensive_change_list(): void
    {
        $component = file_get_contents(__DIR__.'/../../resources/js/modules/timeline/components/timeline-activity-item.tsx');

        $this->assertIsString($component);
        $this->assertStringContainsString('const TimelineActivityItemContent = memo(', $component);
        $this->assertStringContainsString('export function TimelineActivityItem(', $component);
        $this->assertStringContainsString('const [open, setOpen] = useState(false);', $component);
        $this->assertStringContainsString('const [renderChanges, setRenderChanges] = useState(false);', $component);
        $this->assertStringContainsString('window.requestAnimationFrame', $component);
        $this->assertStringContainsString('{open && (', $component);
        $this->assertStringContainsString('{renderChanges ? (', $component);
        $this->assertStringContainsString('<ActivityChangesList changes={item.changes} />', $component);
    }
}
