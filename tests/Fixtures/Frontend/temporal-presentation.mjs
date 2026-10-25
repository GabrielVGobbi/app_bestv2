import assert from 'node:assert/strict';
import http from 'node:http';
import https from 'node:https';
import net from 'node:net';

let networkCalls = 0;
const denyNetwork = () => {
    networkCalls++;
    throw new Error('Network is forbidden in the temporal fixture.');
};
http.request = http.get = https.request = https.get = denyNetwork;
net.connect = net.createConnection = globalThis.fetch = denyNetwork;

const { groupTimelineItems } =
    await import('../../../resources/js/modules/timeline/lib/group-timeline-items.ts');
const { formatDate } = await import('../../../resources/js/lib/utils.ts');
const timezone = process.argv[2];
assert.equal(new Intl.DateTimeFormat().resolvedOptions().timeZone, timezone);
assert.equal(process.env.TZ, timezone);
assert.deepEqual(
    Object.keys(process.env).filter((key) =>
        /^(APP_|DB_|MAIL_|AWS_|ASAA[S_]|SPOTIFY_|NODE_OPTIONS)/.test(key),
    ),
    [],
);

const expectedKeys = {
    UTC: [
        ['2031-05-02', '2031-05-01'],
        ['2031-05-02'],
        ['2031-05-02'],
        ['2031-05-01'],
    ],
    'America/Sao_Paulo': [
        ['2031-05-01'],
        ['2031-05-02', '2031-05-01'],
        ['2031-05-02'],
        ['2031-05-01'],
    ],
    'America/New_York': [
        ['2031-05-01'],
        ['2031-05-01'],
        ['2031-05-02', '2031-05-01'],
        ['2031-05-01'],
    ],
    'Asia/Tokyo': [
        ['2031-05-02'],
        ['2031-05-02'],
        ['2031-05-02'],
        ['2031-05-02', '2031-05-01'],
    ],
};
assert.ok(Object.hasOwn(expectedKeys, timezone));
const scenarios = [
    ['2031-05-02T00:15:00Z', '2031-05-01T23:45:00Z'],
    ['2031-05-02T03:15:00Z', '2031-05-02T02:45:00Z'],
    ['2031-05-02T04:15:00Z', '2031-05-02T03:45:00Z'],
    ['2031-05-01T15:15:00Z', '2031-05-01T14:45:00Z'],
];
const item = (id, createdAt) =>
    Object.freeze({
        type: 'activity',
        id,
        event: 'synthetic',
        label: null,
        description: 'Owned temporal fixture',
        icon: null,
        severity: 'muted',
        causer: null,
        changes: [],
        created_at: createdAt,
    });
const formatter = new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'medium',
    timeZone: timezone,
});
for (const [index, dates] of scenarios.entries()) {
    const items = Object.freeze(
        dates.map((date, position) => item(`case-${index}-${position}`, date)),
    );
    const before = JSON.stringify(items);
    const groups = groupTimelineItems(items);
    const keys = expectedKeys[timezone][index];
    assert.deepEqual(
        groups.map((group) => group.key),
        keys,
        `Local group boundary ${index} in ${timezone}`,
    );
    assert.deepEqual(
        groups.flatMap((group) => group.items),
        items,
    );
    if (keys.length === 1) {
        assert.equal(groups[0].items.length, 2);
    } else {
        assert.equal(groups[0].items.length, 1);
        assert.equal(groups[1].items.length, 1);
    }
    for (const group of groups) {
        assert.equal(
            group.label,
            formatter.format(new Date(group.items[0].created_at)),
        );
        for (const entry of group.items) {
            assert.equal(
                formatter.format(new Date(entry.created_at)),
                group.label,
            );
        }
    }
    assert.equal(JSON.stringify(items), before);
}

const displays = {
    UTC: '02/05/2031, 00:30',
    'America/Sao_Paulo': '01/05/2031, 21:30',
    'America/New_York': '01/05/2031, 20:30',
    'Asia/Tokyo': '02/05/2031, 09:30',
};
assert.equal(formatDate('2031-05-02T00:30:00Z'), displays[timezone]);
assert.equal(formatDate(null), '-');
assert.equal(formatDate(''), '-');
assert.deepEqual(groupTimelineItems([]), []);
const equivalent = [
    item('utc', '2031-05-02T00:30:00Z'),
    item('offset', '2031-05-01T21:30:00-03:00'),
];
const grouped = groupTimelineItems(equivalent);
assert.equal(grouped.length, 1);
assert.deepEqual(grouped[0].items, equivalent);
assert.equal(networkCalls, 0);
process.stdout.write(
    JSON.stringify({
        status: 'passed',
        timezone,
        cases: 7,
        network_calls: networkCalls,
    }) + '\n',
);
