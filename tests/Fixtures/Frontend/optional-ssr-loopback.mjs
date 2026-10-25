import assert from 'node:assert/strict';
import http from 'node:http';
import https from 'node:https';
import { syncBuiltinESMExports } from 'node:module';
import net from 'node:net';
import process from 'node:process';
import tls from 'node:tls';

const port = Number(process.env.OPS004_SSR_PORT);
assert(Number.isInteger(port) && port > 1024 && port < 65536 && port !== 13714);
const listen = http.Server.prototype.listen;
http.Server.prototype.listen = function (...args) {
    const requested = typeof args[0] === 'object' ? args[0].port : args[0];
    assert.equal(
        Number(requested),
        13714,
        'Only the installed SSR server default may be relocated.',
    );
    this.once('listening', () => {
        assert.equal(this.address().address, '127.0.0.1');
        console.log(JSON.stringify({ bound: this.address(), owned: true }));
    });

    return listen.call(this, { port, host: '127.0.0.1' });
};
const denyNetwork = () => {
    throw new Error(
        'SSR outbound transport forbidden in the isolated runtime.',
    );
};
http.request = http.get = https.request = https.get = denyNetwork;
net.connect = net.createConnection = tls.connect = denyNetwork;
globalThis.fetch = denyNetwork;
syncBuiltinESMExports();
