<?php

namespace Tests\Feature;

use Laravel\Boost\Install\Agents\Codex;
use Laravel\Boost\Install\Agents\Cursor;
use Tests\TestCase;

class BoostAgentLayoutTest extends TestCase
{
    public function test_cursor_and_codex_share_one_boost_skills_path(): void
    {
        $cursorPath = app(Cursor::class)->skillsPath();
        $codexPath = app(Codex::class)->skillsPath();

        $this->assertSame($cursorPath, $codexPath);
        $this->assertDirectoryExists(base_path($cursorPath));
    }

    public function test_cursor_skills_symlink_points_at_shared_agents_skills(): void
    {
        $sharedSkills = base_path('.agents/skills');
        $cursorSkills = base_path('.cursor/skills');

        $this->assertDirectoryExists($sharedSkills);
        $this->assertTrue(is_link($cursorSkills), '.cursor/skills should be a symlink');
        $this->assertSame(
            realpath($sharedSkills),
            realpath($cursorSkills),
        );
    }

    public function test_unused_boost_agent_trees_and_brand_svg_are_absent(): void
    {
        $this->assertFileDoesNotExist(base_path('CLAUDE.md'));
        $this->assertDirectoryDoesNotExist(base_path('.claude'));
        $this->assertDirectoryDoesNotExist(base_path('.junie'));
        $this->assertFileDoesNotExist(base_path('.mcp.json'));
        $this->assertFileDoesNotExist(base_path('resources/brand/nerdik_brand_logo.svg'));
        $this->assertFileExists(base_path('resources/brand/nerdik_brand_logo.webp'));
        $this->assertFileExists(base_path('AGENTS.md'));
    }
}
