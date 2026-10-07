<?php
/**
 * Guards the vendored agent skills layout (WPD-40).
 *
 * Skills live in .agents/skills/<name>/, pinned in skills-lock.json, and are
 * exposed to Claude Code via .claude/skills/<name> symlinks. Every vendored
 * skill must be agent-invocable except prototype, which stays Nick-only.
 */

declare(strict_types=1);

$root        = dirname( __DIR__ );
$agents_root = $root . '/.agents/skills';
$claude_root = $root . '/.claude/skills';
$lock_path   = $root . '/skills-lock.json';
$flag        = '/^disable-model-invocation:\s*true\s*$/m';
$nick_only   = [ 'prototype' ];

assert( is_dir( $agents_root ), '.agents/skills/ must exist' );
assert( is_file( $lock_path ), 'skills-lock.json must exist' );

$vendored = array_values(
	array_filter(
		scandir( $agents_root ) ?: [],
		static fn ( string $name ): bool => '.' !== $name[0] && is_dir( $agents_root . '/' . $name )
	)
);
sort( $vendored );

// Case 1: every locked skill is vendored, and nothing unlocked is vendored.
$lock   = json_decode( (string) file_get_contents( $lock_path ), true );
$locked = array_keys( $lock['skills'] ?? [] );
sort( $locked );
assert( [] !== $locked, 'skills-lock.json must pin at least one skill' );
assert( $locked === $vendored, 'skills-lock.json keys must match .agents/skills/ directories' );

// Case 2: .claude/skills/<name> is a relative symlink to the canonical tree.
foreach ( $vendored as $name ) {
	$link = $claude_root . '/' . $name;
	assert( is_link( $link ), ".claude/skills/{$name} must be a symlink" );
	assert( "../../.agents/skills/{$name}" === readlink( $link ), ".claude/skills/{$name} must point to ../../.agents/skills/{$name}" );
}

// Case 3: agents can invoke every vendored skill except the Nick-only ones.
$flagged = array_values(
	array_filter(
		$vendored,
		static fn ( string $name ): bool => 1 === preg_match( $flag, (string) file_get_contents( "{$agents_root}/{$name}/SKILL.md" ) )
	)
);
sort( $flagged );
assert( $nick_only === $flagged, 'only prototype may carry disable-model-invocation: true; flagged: ' . implode( ', ', $flagged ) );

// Case 4: the vendor layout and local patch are documented for agents.
$doc = (string) file_get_contents( $root . '/docs/agents/skills.md' );
assert( str_contains( $doc, 'skills-lock.json' ), 'docs/agents/skills.md must mention skills-lock.json' );
assert( str_contains( $doc, '.agents/skills/' ), 'docs/agents/skills.md must mention .agents/skills/' );
assert( str_contains( $doc, 'disable-model-invocation' ), 'docs/agents/skills.md must explain the local patch' );

echo "test-skills-vendor ok\n";
