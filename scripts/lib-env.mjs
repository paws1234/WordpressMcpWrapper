/**
 * Minimal .env reader and project lookup shared by the kit scripts.
 *
 * Deliberately dependency-free: the kit has no package.json and no node_modules.
 */

import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';

/**
 * Read a project's .env into an object. Handles comments, quoted values, and
 * blanks. Not a full dotenv implementation, just enough for the keys wpdev writes.
 */
export function loadEnv( dir ) {
	const file = path.join( dir, '.env' );

	if ( ! existsSync( file ) ) {
		return {};
	}

	const values = {};

	for ( const line of readFileSync( file, 'utf8' ).split( '\n' ) ) {
		if ( /^\s*#/.test( line ) ) {
			continue;
		}

		const match = /^\s*([A-Za-z0-9_]+)\s*=\s*(.*?)\s*$/.exec( line );

		if ( ! match ) {
			continue;
		}

		let value = match[ 2 ];

		if (
			( value.startsWith( '"' ) && value.endsWith( '"' ) ) ||
			( value.startsWith( "'" ) && value.endsWith( "'" ) )
		) {
			value = value.slice( 1, -1 );
		}

		values[ match[ 1 ] ] = value;
	}

	return values;
}

/**
 * Walk up from start looking for the .wpdev-project marker.
 */
export function findProjectDir( start = process.cwd() ) {
	let dir = path.resolve( start );

	for ( ;; ) {
		if ( existsSync( path.join( dir, '.wpdev-project' ) ) ) {
			return dir;
		}

		const parent = path.dirname( dir );

		if ( parent === dir ) {
			return null;
		}

		dir = parent;
	}
}

/**
 * Resolve the project directory or exit with a helpful message.
 */
export function requireProjectDir() {
	const dir = findProjectDir();

	if ( ! dir ) {
		console.error( 'Not inside a wp-dev project (no .wpdev-project marker found).' );
		console.error( 'Run this from a project directory, or create one with: wpdev new <name>' );
		process.exit( 1 );
	}

	return dir;
}

/**
 * Settings every script needs, with the same defaults wpdev writes into .env.
 */
export function projectSettings( dir ) {
	const env = loadEnv( dir );

	return {
		env,
		dir,
		name: env.PROJECT_NAME ?? path.basename( dir ),
		slug: env.PROJECT_SLUG ?? path.basename( dir ),
		siteUrl: env.SITE_URL ?? `http://localhost:${ env.WP_PORT ?? '8888' }`,
		adminUser: env.ADMIN_USER ?? 'admin',
	};
}
