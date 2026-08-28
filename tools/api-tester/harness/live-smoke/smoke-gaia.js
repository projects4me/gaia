#!/usr/bin/env node
/**
 * Minimal Gaia stand-in for exercising api-tester --mode live without Docker.
 * Handles OAuth token + the live.json write paths, and POSTs V2 envelopes to Hermes.
 */
'use strict';

const http = require('http');
const { randomUUID } = require('crypto');

const PORT = Number(process.env.SMOKE_GAIA_PORT || 18081);
const HERMES_URL = (process.env.HERMES_URL || 'http://127.0.0.1:19000').replace(/\/$/, '');
const HERMES_SECRET = process.env.HERMES_SECRET || 'hermes-dev-secret';
const PROJECT_ID = 'api-test-project-001';
const USER_ID = 'api-test-user-0001';

function readBody(req) {
	return new Promise((resolve, reject) => {
		const chunks = [];
		req.on('data', (c) => chunks.push(c));
		req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
		req.on('error', reject);
	});
}

function send(res, status, body) {
	const text = typeof body === 'string' ? body : JSON.stringify(body);
	res.writeHead(status, {
		'Content-Type': 'application/json',
		'Content-Length': Buffer.byteLength(text)
	});
	res.end(text);
}

function jsonApiResource(type, id, attributes) {
	return {
		data: {
			type,
			id,
			attributes: attributes || {}
		},
		meta: {
			links: {
				self: `/api/v1/${type}/${id}`
			}
		}
	};
}

async function publish(eventName, resourceType, resourceId, changes) {
	const envelope = {
		schemaVersion: 2,
		eventId: randomUUID(),
		eventName,
		occurredAt: new Date().toISOString(),
		projectId: PROJECT_ID,
		resource: { type: resourceType, id: resourceId },
		actorId: USER_ID,
		changes: changes || {},
		meta: { source: 'gaia-smoke' }
	};
	try {
		const response = await fetch(`${HERMES_URL}/publish`, {
			method: 'POST',
			headers: {
				'content-type': 'application/json',
				'x-hermes-secret': HERMES_SECRET
			},
			body: JSON.stringify(envelope)
		});
		if (!response.ok) {
			const text = await response.text();
			console.error(`Hermes publish failed ${response.status}: ${text}`);
		}
	} catch (error) {
		// Match Gaia HermesPublisher fail-open: log and continue.
		console.error('Hermes publish error:', error.message || error);
	}
	return envelope;
}

const server = http.createServer(async (req, res) => {
	try {
		const url = new URL(req.url, `http://127.0.0.1:${PORT}`);
		const path = url.pathname;
		const method = req.method.toUpperCase();
		const raw = await readBody(req);
		let json = null;
		if (raw) {
			try {
				json = JSON.parse(raw);
			} catch (_) {
				json = null;
			}
		}

		if (method === 'POST' && path === '/api/v1/token') {
			return send(res, 200, {
				access_token: 'smoke-token',
				token_type: 'Bearer',
				expires_in: 3600
			});
		}

		if (method === 'POST' && path === '/api/v1/issuestatus') {
			const id = `status-${randomUUID().slice(0, 8)}`;
			return send(res, 201, jsonApiResource('issuestatus', id, {
				name: (json && json.data && json.data.attributes && json.data.attributes.name) || id,
				projectId: PROJECT_ID
			}));
		}

		if (method === 'POST' && path === '/api/v1/issue') {
			const id = `issue-${randomUUID().slice(0, 8)}`;
			const attrs = (json && json.data && json.data.attributes) || {};
			await publish('issue.created', 'issue', id, {
				subject: attrs.subject || null,
				statusId: attrs.statusId || null
			});
			return send(res, 201, jsonApiResource('issue', id, attrs));
		}

		if (method === 'PATCH' && path.startsWith('/api/v1/issue/')) {
			const id = path.split('/').pop();
			const attrs = (json && json.data && json.data.attributes) || {};
			await publish('issue.status.changed', 'issue', id, {
				statusId: attrs.statusId || null
			});
			return send(res, 200, jsonApiResource('issue', id, attrs));
		}

		if (method === 'POST' && path === '/api/v1/conversationroom') {
			const id = `room-${randomUUID().slice(0, 8)}`;
			const attrs = (json && json.data && json.data.attributes) || {};
			await publish('conversation.created', 'conversationroom', id, {
				subject: attrs.subject || null
			});
			return send(res, 201, jsonApiResource('conversationroom', id, attrs));
		}

		if (method === 'POST' && path === '/api/v1/comment') {
			const id = `comment-${randomUUID().slice(0, 8)}`;
			const attrs = (json && json.data && json.data.attributes) || {};
			await publish('conversation.comment.created', 'comment', id, {
				comment: attrs.comment || null,
				relatedTo: attrs.relatedTo || null,
				relatedId: attrs.relatedId || null
			});
			return send(res, 201, jsonApiResource('comment', id, attrs));
		}

		return send(res, 404, { error: `no route ${method} ${path}` });
	} catch (error) {
		console.error(error);
		return send(res, 500, { error: error.message || String(error) });
	}
});

server.listen(PORT, '127.0.0.1', () => {
	console.log(`smoke-gaia on http://127.0.0.1:${PORT} → Hermes ${HERMES_URL}`);
});
