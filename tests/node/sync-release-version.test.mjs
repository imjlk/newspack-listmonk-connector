import assert from 'node:assert/strict';
import test from 'node:test';

import { getReadmeChangelogEntry } from '../../scripts/sync-release-version.mjs';

test( 'preserves changelog continuation and nested-list indentation', () => {
	const changelog = `# Changelog

## 1.2.3

### Fixed

- Parent item
  continuation text
    - Nested item

## 1.2.2

- Previous item
`;

	assert.equal(
		getReadmeChangelogEntry( changelog, '1.2.3' ),
		`= 1.2.3 =
* Fixed: Parent item
  continuation text
    - Nested item
`
	);
} );
