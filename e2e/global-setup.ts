export default async function globalSetup() {
    const baseURL = process.env.BASE_URL || 'http://localhost:8080';
    const seedURL = `${baseURL}/api/test/seed`;

    console.log('Verifying app is reachable...');
    const check = await fetch(`${baseURL}/login`).catch(() => null);
    if (!check?.ok) {
        throw new Error(`App not reachable at ${baseURL}. Is the server running?`);
    }

    console.log('Seeding test database...');
    const res = await fetch(seedURL, { method: 'POST' });
    if (!res.ok) {
        throw new Error(`Failed to seed database: ${res.status} ${await res.text()}`);
    }
    console.log('Database seeded successfully.');
}
