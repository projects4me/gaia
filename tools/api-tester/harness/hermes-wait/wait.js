#!/usr/bin/env node
/**
 * Subscribe to Hermes domain:event before a Gaia write, then exit with matched
 * envelopes as JSON on stdout (or write --result-file).
 *
 * Protocol:
 *   1. Connect with OAuth token, intents:set for expected events
 *   2. Write --ready-file (or print ARMED) when subscribed
 *   3. Wait for matching domain:event(s) until timeout
 *   4. Exit 0 with JSON { "matched": [ ...envelopes ] }; non-zero on failure
 */
'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

function prependNodePath(dir) {
	if (!dir || !fs.existsSync(dir)) {
		return;
	}
	const current = process.env.NODE_PATH ? process.env.NODE_PATH.split(path.delimiter) : [];
	if (!current.includes(dir)) {
		process.env.NODE_PATH = [dir].concat(current).join(path.delimiter);
		Module._initPaths();
	}
}

prependNodePath(path.join(__dirname, 'node_modules'));
// .../gaia/tools/api-tester/harness/hermes-wait → .../hammad-hassan/hermes/node_modules
prependNodePath(path.resolve(__dirname, '../../../../../hermes/node_modules'));

const { io: createClient } = require('socket.io-client');

function parseArgs(argv) {
	const options = {
		hermesUrl: null,
		token: null,
		expect: [],
		timeoutMs: 5000,
		readyFile: null,
		resultFile: null
	};

	for (let i = 2; i < argv.length; i++) {
		const arg = argv[i];
		const next = argv[i + 1];
		if (arg === '--hermes-url' && next) {
			options.hermesUrl = next.replace(/\/$/, '');
			i++;
		} else if (arg === '--token' && next) {
			options.token = next;
			i++;
		} else if (arg === '--expect' && next) {
			options.expect = JSON.parse(next);
			i++;
		} else if (arg === '--expect-file' && next) {
			options.expect = JSON.parse(fs.readFileSync(next, 'utf8'));
			i++;
		} else if (arg === '--timeout-ms' && next) {
			options.timeoutMs = Number(next);
			i++;
		} else if (arg === '--ready-file' && next) {
			options.readyFile = next;
			i++;
		} else if (arg === '--result-file' && next) {
			options.resultFile = next;
			i++;
		} else if (arg === '--help') {
			printHelp();
			process.exit(0);
		}
	}

	return options;
}

function printHelp() {
	process.stdout.write(`Usage:
  node wait.js --hermes-url <url> --token <oauth> --expect '<json>' [options]

Options:
  --expect-file <path>   Read expect array from JSON file
  --timeout-ms <n>       Wait timeout after armed (default 5000)
  --ready-file <path>    Written when intents are accepted
  --result-file <path>   Write result JSON here (also printed on stdout)
`);
}

function matchesExpect(envelope, expect) {
	if (!envelope || typeof envelope !== 'object') {
		return false;
	}
	if (expect.eventName && envelope.eventName !== expect.eventName) {
		return false;
	}
	if (expect.projectId && envelope.projectId !== expect.projectId) {
		return false;
	}
	if (expect.resourceType) {
		const type = envelope.resource && envelope.resource.type;
		if (type !== expect.resourceType) {
			return false;
		}
	}
	if (expect.resourceId) {
		const id = envelope.resource && envelope.resource.id;
		if (String(id) !== String(expect.resourceId)) {
			return false;
		}
	}
	return true;
}

function fail(message, code = 1) {
	const payload = { ok: false, error: message };
	process.stderr.write(message + '\n');
	process.stdout.write(JSON.stringify(payload) + '\n');
	process.exit(code);
}

async function main() {
	const options = parseArgs(process.argv);

	if (!options.hermesUrl) {
		fail('Missing --hermes-url');
	}
	if (!options.token) {
		fail('Missing --token');
	}
	if (!Array.isArray(options.expect) || options.expect.length === 0) {
		fail('Missing --expect / --expect-file (non-empty array required)');
	}

	const pending = options.expect.map((item, index) => ({
		index,
		item,
		matched: null
	}));

	const client = createClient(options.hermesUrl, {
		auth: { token: options.token },
		transports: ['websocket'],
		reconnection: false
	});

	const cleanup = () => {
		try {
			client.removeAllListeners();
			client.close();
		} catch (_) {
			/* ignore */
		}
	};

	await new Promise((resolve, reject) => {
		const timer = setTimeout(
			() => reject(new Error(`timed out connecting to Hermes at ${options.hermesUrl}`)),
			Math.min(options.timeoutMs, 5000)
		);
		client.once('connect', () => {
			clearTimeout(timer);
			resolve();
		});
		client.once('connect_error', (error) => {
			clearTimeout(timer);
			reject(new Error(`Hermes socket connect failed: ${error.message}`));
		});
	}).catch((error) => {
		cleanup();
		fail(error.message);
	});

	const intents = pending.map(({ item }) => ({
		projectId: item.projectId,
		eventName: item.eventName
	}));

	let ack;
	try {
		ack = await client.emitWithAck('intents:set', {
			protocolVersion: 2,
			revision: 0,
			intents
		});
	} catch (error) {
		cleanup();
		fail(`intents:set failed: ${error.message}`);
	}

	if (!ack || !Array.isArray(ack.accepted) || ack.accepted.length === 0) {
		cleanup();
		fail(`intents:set rejected: ${JSON.stringify(ack || null)}`);
	}

	if (options.readyFile) {
		fs.writeFileSync(options.readyFile, 'ready\n');
	} else {
		process.stdout.write('ARMED\n');
	}

	const matched = await new Promise((resolve, reject) => {
		const timer = setTimeout(() => {
			reject(new Error(
				`timed out waiting for domain:event after ${options.timeoutMs}ms; still pending: ${
					JSON.stringify(pending.filter((p) => !p.matched).map((p) => p.item))
				}`
			));
		}, options.timeoutMs);

		client.on('domain:event', (envelope) => {
			for (const entry of pending) {
				if (entry.matched) {
					continue;
				}
				if (matchesExpect(envelope, entry.item)) {
					entry.matched = envelope;
				}
			}
			if (pending.every((entry) => entry.matched)) {
				clearTimeout(timer);
				resolve(pending.map((entry) => entry.matched));
			}
		});
	}).catch((error) => {
		cleanup();
		fail(error.message);
	});

	cleanup();

	const result = { ok: true, matched };
	const text = JSON.stringify(result);
	if (options.resultFile) {
		fs.writeFileSync(options.resultFile, text + '\n');
	}
	process.stdout.write(text + '\n');
	process.exit(0);
}

main().catch((error) => {
	fail(error && error.message ? error.message : String(error));
});
