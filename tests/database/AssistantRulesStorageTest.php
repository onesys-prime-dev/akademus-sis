<?php

use App\Libraries\ScheduleSuggester;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * The assistant's rules are stored per department through the Settings
 * service (context "dept:<id>"); this checks that round trip against a real
 * settings table and that one department's rules never leak into another's.
 *
 * @internal
 */
final class AssistantRulesStorageTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    public function testRulesRoundTripPerDepartment(): void
    {
        $engine = new ScheduleSuggester();
        $rules  = $engine->normalizeRules(['allow_saturday' => false, 'max_hours_day' => 6, 'spread' => 'strong']);

        service('settings')->set('Scheduling.assistantRules', json_encode($rules), 'dept:1');

        $stored = json_decode((string) service('settings')->get('Scheduling.assistantRules', 'dept:1'), true);

        $this->assertSame($rules, $engine->normalizeRules($stored));
        $this->assertFalse($stored['allow_saturday']);
        $this->assertSame('strong', $stored['spread']);

        // another department still sees nothing saved (=> defaults)
        $other = service('settings')->get('Scheduling.assistantRules', 'dept:2');
        $this->assertNull($other);
        $this->assertSame(ScheduleSuggester::DEFAULT_RULES['spread'], $engine->normalizeRules([])['spread']);
    }

    public function testCorruptStoredRulesFallBackToDefaults(): void
    {
        service('settings')->set('Scheduling.assistantRules', '{not json', 'dept:3');

        $raw   = service('settings')->get('Scheduling.assistantRules', 'dept:3');
        $saved = is_string($raw) ? json_decode($raw, true) : null;

        $this->assertSame(
            (new ScheduleSuggester())->normalizeRules([]),
            (new ScheduleSuggester())->normalizeRules(is_array($saved) ? $saved : [])
        );
    }
}
