#!/usr/bin/env node
/**
 * Populate the kit's shared download cache.
 *
 * One cache for every project. The image build COPYs from here, so a new project
 * never re-downloads WordPress tooling and the build itself needs no network.
 *
 * Usage:
 *   node scripts/fetch-packages.mjs            # fetch anything missing
 *   node scripts/fetch-packages.mjs --force    # re-download everything
 */

import { createWriteStream } from 'node:fs';
import { mkdir, stat, chmod, rm } from 'node:fs/promises';
import { pipeline } from 'node:stream/promises';
import { Readable } from 'node:stream';
import path from 'node:path';

const KIT = path.resolve( import.meta.dirname, '..' );
const BIN_DIR = path.join( KIT, 'cache', 'bin' );
const PKG_DIR = path.join( KIT, 'cache', 'packages' );

const TARGETS = [
	{
		label: 'WP-CLI',
		url: 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar',
		dest: path.join( BIN_DIR, 'wp' ),
		mode: 0o755,
		minBytes: 1_000_000,
	},
	{
		label: 'Elementor',
		url: 'https://downloads.wordpress.org/plugin/elementor.zip',
		dest: path.join( PKG_DIR, 'elementor.zip' ),
		minBytes: 1_000_000,
	},
	{
		label: 'MCP Adapter',
		// Not published to the plugin directory, so it comes from the GitHub release.
		url: 'https://github.com/WordPress/mcp-adapter/releases/download/v0.6.1/mcp-adapter.zip',
		dest: path.join( PKG_DIR, 'mcp-adapter.zip' ),
		minBytes: 10_000,
	},
	{
		label: 'Hello Elementor',
		url: 'https://downloads.wordpress.org/theme/hello-elementor.zip',
		dest: path.join( PKG_DIR, 'hello-elementor.zip' ),
		minBytes: 10_000,
	},
];

const force = process.argv.includes( '--force' );

async function sizeOf( file ) {
	try {
		return ( await stat( file ) ).size;
	} catch {
		return null;
	}
}

async function fetchOne( { label, url, dest, mode, minBytes } ) {
	const existing = await sizeOf( dest );

	if ( existing !== null && ! force ) {
		console.log( `  ${ label }: cached (${ existing.toLocaleString() } bytes)` );
		return;
	}

	console.log( `  ${ label }: downloading` );
	await mkdir( path.dirname( dest ), { recursive: true } );

	const response = await fetch( url, { redirect: 'follow' } );

	if ( ! response.ok ) {
		throw new Error( `${ label }: ${ response.status } ${ response.statusText } for ${ url }` );
	}

	await pipeline( Readable.fromWeb( response.body ), createWriteStream( dest ) );

	const size = await sizeOf( dest );

	if ( size < minBytes ) {
		await rm( dest, { force: true } );
		throw new Error(
			`${ label }: downloaded ${ size } bytes, expected at least ${ minBytes }. File removed; check the URL.`
		);
	}

	if ( mode ) {
		await chmod( dest, mode );
	}

	console.log( `  ${ label }: ok (${ size.toLocaleString() } bytes)` );
}

console.log( `Shared cache: ${ PKG_DIR }` );

for ( const target of TARGETS ) {
	await fetchOne( target );
}

console.log( '\nCache ready.' );
