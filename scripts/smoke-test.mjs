#!/usr/bin/env node
/**
 * End-to-end smoke test for a wp-dev project.
 *
 * Talks to the site the way an AI client does: over the MCP HTTP transport,
 * authenticated with a throwaway WordPress application password. Creates a page,
 * builds a layout, patches a widget, reads it back, and cleans up after itself.
 */

import { execFileSync } from 'node:child_process';
import { findProjectDir, requireProjectDir, projectSettings } from './lib-env.mjs';

// Accept an explicit project directory, otherwise walk up from the current one.
const projectDir = process.argv[ 2 ]
	? findProjectDir( process.argv[ 2 ] )
	: requireProjectDir();

if ( ! projectDir ) {
	console.error( `Not a wp-dev project: ${ process.argv[ 2 ] }` );
	process.exit( 1 );
}

const { name, siteUrl, adminUser } = projectSettings( projectDir );

const ENDPOINT = `${ siteUrl }/wp-json/mcp/mcp-adapter-default-server`;
const PREFIX = 'wp-agent-bridge/';

const passwordName = `smoke-${ Date.now() }`;

let password = '';
let sessionId = '';
let rpcId = 0;

const results = [];
const created = {};

function wp( args ) {
	return execFileSync( 'docker', [ 'compose', 'run', '--rm', '-T', 'cli', ...args ], {
		encoding: 'utf8',
		cwd: projectDir,
		stdio: [ 'ignore', 'pipe', 'ignore' ],
	} ).trim();
}

function step( label, fn ) {
	return Promise.resolve()
		.then( fn )
		.then( ( detail ) => {
			results.push( { label, ok: true } );
			console.log( `  ok    ${ label }${ detail ? `  ${ detail }` : '' }` );
			return true;
		} )
		.catch( ( error ) => {
			results.push( { label, ok: false } );
			console.log( `  FAIL  ${ label }` );
			console.log( `        ${ error.message }` );
			return false;
		} );
}

function assert( condition, message ) {
	if ( ! condition ) {
		throw new Error( message );
	}
}

/** MCP responses are either plain JSON or an SSE stream carrying JSON. */
function parseResponse( text ) {
	const trimmed = text.trim();

	if ( trimmed.startsWith( '{' ) ) {
		return JSON.parse( trimmed );
	}

	const dataLines = trimmed
		.split( '\n' )
		.filter( ( line ) => line.startsWith( 'data:' ) )
		.map( ( line ) => line.slice( 5 ).trim() );

	assert( dataLines.length > 0, `Unrecognised response: ${ trimmed.slice( 0, 200 ) }` );

	return JSON.parse( dataLines[ dataLines.length - 1 ] );
}

async function rpc( method, params ) {
	const response = await fetch( ENDPOINT, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json, text/event-stream',
			Authorization: 'Basic ' + Buffer.from( `${ adminUser }:${ password }` ).toString( 'base64' ),
			...( sessionId ? { 'Mcp-Session-Id': sessionId } : {} ),
		},
		body: JSON.stringify( { jsonrpc: '2.0', id: ++rpcId, method, params } ),
	} );

	const assigned = response.headers.get( 'mcp-session-id' );

	if ( assigned ) {
		sessionId = assigned;
	}

	const text = await response.text();

	if ( ! response.ok ) {
		throw new Error( `${ method } -> HTTP ${ response.status }: ${ text.slice( 0, 300 ) }` );
	}

	return parseResponse( text );
}

function textOf( block ) {
	return ( block.content ?? [] )
		.map( ( item ) => item.text ?? '' )
		.join( '\n' )
		.trim();
}

/**
 * Tool results arrive either as structuredContent or as a JSON string inside a text
 * content block, depending on the adapter version.
 */
function payloadOf( block ) {
	if ( block.structuredContent ) {
		return block.structuredContent;
	}

	const text = textOf( block );

	try {
		return JSON.parse( text );
	} catch {
		return { raw: text };
	}
}

async function callAbility( abilityName, parameters ) {
	const response = await rpc( 'tools/call', {
		name: 'mcp-adapter-execute-ability',
		arguments: { ability_name: abilityName, parameters },
	} );

	const block = response.result;

	assert( block, `No result for ${ abilityName }: ${ JSON.stringify( response ).slice( 0, 300 ) }` );
	assert( ! block.isError, `${ abilityName } failed: ${ textOf( block ) }` );

	return payloadOf( block );
}

/** Depth-first search for the first node matching a predicate. */
function findNode( nodes, predicate ) {
	for ( const node of nodes ?? [] ) {
		if ( predicate( node ) ) {
			return node;
		}

		const found = findNode( node.children, predicate );

		if ( found ) {
			return found;
		}
	}

	return null;
}

function cleanup() {
	// Remove the content this run created so repeat runs stay idempotent.
	for ( const postId of [ created.postId, created.portfolioPostId ] ) {
		if ( ! postId ) {
			continue;
		}

		try {
			wp( [ 'post', 'delete', String( postId ), '--force' ] );
		} catch ( error ) {
			console.log( `Could not delete post ${ postId }: ${ error.message }` );
		}
	}

	try {
		const csv = wp( [ 'user', 'application-password', 'list', adminUser, '--fields=name,uuid', '--format=csv' ] );
		const stale = csv
			.split( '\n' )
			.slice( 1 )
			.map( ( line ) => line.split( ',' ) )
			.filter( ( [ entryName ] ) => ( entryName ?? '' ).startsWith( 'smoke-' ) );

		for ( const [ , uuid ] of stale ) {
			wp( [ 'user', 'application-password', 'delete', adminUser, uuid ] );
		}

		if ( stale.length ) {
			console.log( `\nCleaned up ${ stale.length } temporary application password(s).` );
		}
	} catch ( error ) {
		console.log( `\nCould not clean up application passwords: ${ error.message }` );
	}
}

console.log( `Smoke testing ${ name } at ${ siteUrl }\n` );

try {
	await step( 'create application password', () => {
		password = wp( [ 'user', 'application-password', 'create', adminUser, passwordName, '--porcelain' ] );
		assert( password.length >= 20, `unexpected password length: ${ password.length }` );
		return `${ password.length } chars`;
	} );

	if ( ! password ) {
		throw new Error( 'Cannot continue without credentials.' );
	}

	await step( 'MCP initialize', async () => {
		const response = await rpc( 'initialize', {
			protocolVersion: '2025-06-18',
			capabilities: {},
			clientInfo: { name: 'wpdev-smoke', version: '1.0.0' },
		} );

		assert( sessionId, 'server did not return an Mcp-Session-Id header' );
		assert( response.result?.serverInfo, 'no serverInfo in the initialize result' );
		return response.result.serverInfo.name;
	} );

	await step( 'abilities are exposed over MCP', async () => {
		const response = await rpc( 'tools/call', {
			name: 'mcp-adapter-discover-abilities',
			arguments: {},
		} );

		const payload = payloadOf( response.result );
		const list = Array.isArray( payload ) ? payload : payload.abilities ?? [];
		const names = list
			.map( ( entry ) => ( typeof entry === 'string' ? entry : entry?.name ) )
			.filter( ( entryName ) => typeof entryName === 'string' && entryName.startsWith( PREFIX ) );

		assert(
			names.includes( `${ PREFIX }render-layout-recipe` ),
			`render-layout-recipe not found among ${ list.length } abilities. Raw: ${ JSON.stringify( payload ).slice( 0, 300 ) }`
		);

		return `${ names.length } of ${ list.length } abilities`;
	} );

	await step( 'get-environment-info', async () => {
		const info = await callAbility( `${ PREFIX }get-environment-info`, {} );
		const data = info.data ?? info;
		assert( data.elementor?.active, 'Elementor reported as inactive' );
		return `Elementor ${ data.elementor.version }, ${ data.elementor.layout_mode } mode`;
	} );

	await step( 'create-elementor-page', async () => {
		const result = await callAbility( `${ PREFIX }create-elementor-page`, {
			title: 'Smoke Test Landing Page',
			status: 'publish',
			template: 'canvas',
		} );

		const data = result.data ?? result;
		created.postId = data.post_id;
		assert( created.postId, 'no post_id returned' );
		return `post ${ created.postId }`;
	} );

	await step( 'render-layout-recipe (hero)', async () => {
		const result = await callAbility( `${ PREFIX }render-layout-recipe`, {
			recipe: 'hero',
			post_id: created.postId,
			mode: 'replace',
			args: {
				heading: 'Built by an agent',
				subheading: 'This page was assembled through MCP.',
				button_text: 'Read more',
				button_url: '#more',
			},
		} );

		const data = result.data ?? result;
		assert( data.saved === true, `recipe was not saved: ${ JSON.stringify( data ).slice( 0, 200 ) }` );
		return `${ data.top_level_elements } top-level element(s)`;
	} );

	await step( 'get-page-structure', async () => {
		const result = await callAbility( `${ PREFIX }get-page-structure`, { post_id: created.postId } );
		const data = result.data ?? result;

		assert( data.built_with_elementor === true, 'page is not marked as built with Elementor' );
		assert( data.total_elements > 3, `expected several elements, got ${ data.total_elements }` );
		assert( data.structure?.[ 0 ]?.type === 'container', `first element is ${ data.structure?.[ 0 ]?.type }` );

		created.headingId = findNode( data.structure, ( node ) => node.widget === 'heading' )?.id;
		created.editUrl = data.edit_url;

		return `${ data.total_elements } elements`;
	} );

	await step( 'update-widget', async () => {
		assert( created.headingId, 'no heading element found to patch' );

		const result = await callAbility( `${ PREFIX }update-widget`, {
			post_id: created.postId,
			element_id: created.headingId,
			settings: { title: 'Patched by an agent' },
		} );

		const data = result.data ?? result;
		assert( data.element?.settings?.title === 'Patched by an agent', 'heading title was not updated' );
		return `element ${ created.headingId }`;
	} );

	await step( 'create-post (portfolio + meta)', async () => {
		const result = await callAbility( `${ PREFIX }create-post`, {
			post_type: 'portfolio',
			title: 'Smoke Test Project',
			status: 'draft',
			meta: { portfolio_client: 'Acme', portfolio_year: 2026 },
		} );

		const data = result.data ?? result;
		assert( data.post_id, 'no post_id returned' );
		created.portfolioPostId = data.post_id;
		assert(
			( data.meta_applied ?? [] ).includes( 'portfolio_client' ),
			`portfolio_client was not applied. Applied: ${ ( data.meta_applied ?? [] ).join( ', ' ) || 'none' }`
		);
		return `post ${ data.post_id }, meta ${ ( data.meta_applied ?? [] ).join( '+' ) }`;
	} );

	await step( 'front end renders the page', async () => {
		const response = await fetch( `${ siteUrl }/?page_id=${ created.postId }` );
		const html = await response.text();

		assert( response.ok, `HTTP ${ response.status }` );
		assert( html.includes( 'Patched by an agent' ), 'the patched heading is missing from the rendered HTML' );

		return 'heading present';
	} );
} catch ( error ) {
	console.log( `\nAborted: ${ error.message }` );
	process.exitCode = 1;
} finally {
	cleanup();
}

const failed = results.filter( ( result ) => ! result.ok );

console.log( `\n${ results.length - failed.length }/${ results.length } checks passed` );

if ( created.editUrl ) {
	console.log( `Edit in Elementor: ${ created.editUrl }` );
}

if ( failed.length ) {
	process.exitCode = 1;
}
