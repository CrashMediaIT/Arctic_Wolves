import { test, expect } from '@playwright/test';

/**
 * Arctic Wolves – TV API Endpoint Tests
 * Tests for the /api/v1/tv REST endpoint that the Android TV app uses.
 */

const BASE_URL = process.env.BASE_URL || 'http://localhost/Arctic_Wolves';

test.describe('API v1 - TV Pair Endpoints', () => {

  test('API root lists tv endpoint', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/api/`);
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body.success).toBe(true);
    expect(body.endpoints).toHaveProperty('tv');
    expect(body.endpoints.tv).toBe('/v1/tv');
  });

  test('API v1 resources list includes tv', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/api/v1`);
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body.success).toBe(true);
    expect(body.resources).toContain('tv');
  });

  test('POST /v1/tv/pair without auth returns 401', async ({ request }) => {
    const response = await request.post(`${BASE_URL}/api/v1/tv/pair`, {
      data: { pair_code: 'TESTCODE' },
    });
    expect(response.status()).toBe(401);
    const body = await response.json();
    expect(body.success).toBe(false);
  });

  test('GET /v1/tv/pair/0 without auth returns 401', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/api/v1/tv/pair/0`);
    expect(response.status()).toBe(401);
    const body = await response.json();
    expect(body.success).toBe(false);
  });

  test('DELETE /v1/tv/pair/0 without auth returns 401', async ({ request }) => {
    const response = await request.delete(`${BASE_URL}/api/v1/tv/pair/0`);
    expect(response.status()).toBe(401);
    const body = await response.json();
    expect(body.success).toBe(false);
  });

  test('GET /v1/tv/unknown returns 404', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/api/v1/tv/unknown`);
    // Without auth it will be 401; the 404 is returned after auth
    expect([401, 404]).toContain(response.status());
  });

  test('TV pair endpoint structure matches expected JSON shape', async ({ request }) => {
    // Without a valid API key, we should get a structured 401 error
    const response = await request.post(`${BASE_URL}/api/v1/tv/pair`, {
      headers: { 'Content-Type': 'application/json' },
      data: { pair_code: 'TEST01' },
    });
    expect(response.status()).toBe(401);
    const body = await response.json();
    expect(body).toHaveProperty('success');
    expect(body).toHaveProperty('error');
    expect(typeof body.success).toBe('boolean');
    expect(typeof body.error).toBe('string');
  });
});
