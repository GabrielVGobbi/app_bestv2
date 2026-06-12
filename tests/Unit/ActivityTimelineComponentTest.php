<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ActivityTimelineComponentTest extends TestCase
{
    public function test_activity_timeline_can_render_without_header(): void
    {
        $component = file_get_contents(__DIR__.'/../../resources/js/modules/timeline/components/activity-timeline.tsx');
        $playground = file_get_contents(__DIR__.'/../../resources/js/pages/admin/dev/components/activity-timeline.tsx');

        $this->assertIsString($component);
        $this->assertIsString($playground);

        $this->assertStringContainsString('showHeader?: boolean;', $component);
        $this->assertStringContainsString('const shouldShowHeader = showHeader && title !== null;', $component);
        $this->assertStringContainsString('{shouldShowHeader && (', $component);
        $this->assertStringNotContainsString('title !== null && (', $component);

        $this->assertStringContainsString('const [showHeader, setShowHeader] = useState(true);', $playground);
        $this->assertStringContainsString('showHeader={showHeader}', $playground);
        $this->assertStringContainsString('showHeader={false}', $playground);
    }

    public function test_activity_detail_dialog_mounts_heavy_content_only_when_open(): void
    {
        $component = file_get_contents(__DIR__.'/../../resources/js/modules/timeline/components/timeline-activity-item.tsx');

        $this->assertIsString($component);
        $this->assertStringContainsString('const [detailsOpen, setDetailsOpen] = useState(false);', $component);
        $this->assertStringContainsString('<Dialog open={detailsOpen} onOpenChange={setDetailsOpen}>', $component);
        $this->assertStringContainsString('{detailsOpen && (', $component);
    }
}
