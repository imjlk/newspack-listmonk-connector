#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const rootDir = process.cwd();
const checkOnly = process.argv.includes( '--check' );
const pluginFile = 'wp-typia-newsletter-connector.php';
const packageJsonPath = path.join( rootDir, 'package.json' );
const composerJsonPath = path.join( rootDir, 'composer.json' );
const pluginPath = path.join( rootDir, pluginFile );
const readmePath = path.join( rootDir, 'readme.txt' );
const changelogPath = path.join( rootDir, 'CHANGELOG.md' );

function escapeRegExp( value ) {
	return value.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}

function replaceSingle( content, pattern, replacement, label ) {
	const flags = pattern.flags.includes( 'g' )
		? pattern.flags
		: `${ pattern.flags }g`;
	const matches = content.match( new RegExp( pattern.source, flags ) ) ?? [];

	if ( matches.length !== 1 ) {
		throw new Error(
			`Expected exactly one ${ label }, found ${ matches.length }.`
		);
	}

	return content.replace( pattern, replacement );
}

export function getReadmeChangelogEntry( changelog, version ) {
	const lines = changelog.split( /\r?\n/ );
	const versionHeading = new RegExp(
		`^##\\s+${ escapeRegExp( version ) }(?:\\s|$)`
	);
	const startIndex = lines.findIndex( ( line ) => versionHeading.test( line ) );

	if ( startIndex === -1 ) {
		throw new Error( `CHANGELOG.md has no ${ version } release section.` );
	}

	const endOffset = lines
		.slice( startIndex + 1 )
		.findIndex( ( line ) => /^##\s+/.test( line ) );
	const endIndex =
		endOffset === -1 ? lines.length : startIndex + 1 + endOffset;
	const releaseLines = lines.slice( startIndex + 1, endIndex );
	const bullets = [];
	let category = '';

	for ( const line of releaseLines ) {
		const categoryMatch = line.match( /^###\s+(.+?)\s*$/ );
		if ( categoryMatch ) {
			category = categoryMatch[ 1 ];
			continue;
		}

		const bulletMatch = line.match( /^-\s+(.+?)\s*$/ );
		if ( bulletMatch ) {
			const prefix = category ? `${ category }: ` : '';
			bullets.push( `* ${ prefix }${ bulletMatch[ 1 ] }` );
			continue;
		}

		if ( bullets.length > 0 && /^\s{2,}\S/.test( line ) ) {
			const indentation = line.match( /^\s+/ )?.[ 0 ] ?? '';
			bullets[ bullets.length - 1 ] += `\n${ indentation }${ line.trimStart() }`;
		}
	}

	if ( bullets.length === 0 ) {
		throw new Error(
			`CHANGELOG.md ${ version } section has no release notes.`
		);
	}

	return `= ${ version } =\n${ bullets.join( '\n' ) }\n`;
}

function syncReadmeChangelog( readme, changelog, version ) {
	const changelogHeading = '== Changelog ==';
	const headingIndex = readme.indexOf( changelogHeading );
	if ( headingIndex === -1 ) {
		throw new Error( 'readme.txt has no Changelog section.' );
	}

	const contentStart = headingIndex + changelogHeading.length;
	const prefix = readme.slice( 0, contentStart );
	const changelogBody = readme.slice( contentStart );
	const versionPattern = new RegExp(
		`^= ${ escapeRegExp( version ) } =\\s*$`,
		'm'
	);
	const versionMatch = versionPattern.exec( changelogBody );
	const entry = getReadmeChangelogEntry( changelog, version );

	if ( ! versionMatch ) {
		const existingBody = changelogBody.trimStart();
		const separator = existingBody ? '\n' : '';

		return `${ prefix }\n\n${ entry }${ separator }${ existingBody }`;
	}

	const sectionStart = versionMatch.index;
	const afterHeading = sectionStart + versionMatch[ 0 ].length;
	const remainingBody = changelogBody.slice( afterHeading );
	const nextSectionMatch = /^= [^=\n].+ =\s*$/m.exec( remainingBody );
	const sectionEnd = nextSectionMatch
		? afterHeading + nextSectionMatch.index
		: changelogBody.length;
	const suffix = changelogBody.slice( sectionEnd ).trimStart();
	const separator = suffix ? '\n' : '';

	return `${ prefix }${ changelogBody.slice( 0, sectionStart ) }${ entry }${ separator }${ suffix }`;
}

function main() {
	const composerJson = JSON.parse(
		fs.readFileSync( composerJsonPath, 'utf8' )
	);
	const packageJson = JSON.parse(
		fs.readFileSync( packageJsonPath, 'utf8' )
	);
	const version = String( composerJson.version ?? '' );

	if ( ! /^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/.test( version ) ) {
		throw new Error( `Invalid Composer package version: ${ version || '(missing)' }` );
	}

	const packageJsonSource = fs.readFileSync( packageJsonPath, 'utf8' );
	const pluginSource = fs.readFileSync( pluginPath, 'utf8' );
	const readmeSource = fs.readFileSync( readmePath, 'utf8' );
	const changelogSource = fs.readFileSync( changelogPath, 'utf8' );
	const syncedPackageJson = replaceSingle(
		packageJsonSource,
		/^(\s*"version":\s*)"[^"]+"(,?\s*)$/m,
		`$1"${ version }"$2`,
		'package.json version'
	);
	const syncedPlugin = replaceSingle(
		replaceSingle(
			pluginSource,
			/^(\s*\*\s*Version:\s*)[^\s]+(\s*)$/m,
			`$1${ version }$2`,
			'plugin header version'
		),
		/(define\(\s*'NEWSPACK_LISTMONK_CONNECTOR_VERSION'\s*,\s*)'[^']+'(\s*\))/,
		`$1'${ version }'$2`,
		'plugin version constant'
	);
	const syncedStableTag = replaceSingle(
		readmeSource,
		/^(Stable tag:\s*)[^\s]+(\s*)$/m,
		`$1${ version }$2`,
		'readme Stable tag'
	);
	const syncedReadme = syncReadmeChangelog(
		syncedStableTag,
		changelogSource,
		version
	);
	const updates = [
		[ packageJsonPath, packageJsonSource, syncedPackageJson ],
		[ pluginPath, pluginSource, syncedPlugin ],
		[ readmePath, readmeSource, syncedReadme ],
	];
	const changed = updates.filter(
		( [ , original, synchronized ] ) => original !== synchronized
	);

	if ( checkOnly && changed.length > 0 ) {
		const paths = changed
			.map( ( [ filePath ] ) => path.relative( rootDir, filePath ) )
			.join( ', ' );
		throw new Error(
			`Release version metadata is out of sync in: ${ paths }. Run pnpm release:sync-version.`
		);
	}

	if ( ! checkOnly ) {
		for ( const [ filePath, original, synchronized ] of changed ) {
			if ( original !== synchronized ) {
				fs.writeFileSync( filePath, synchronized, 'utf8' );
			}
		}
	}

	console.log(
		checkOnly
			? `Release version ${ version } is synchronized.`
			: `Synchronized WordPress release metadata to ${ version }.`
	);
}

if (
	process.argv[ 1 ] &&
	import.meta.url === pathToFileURL( path.resolve( process.argv[ 1 ] ) ).href
) {
	main();
}
