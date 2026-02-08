const DEFAULT_HEADERS = {
  "Content-Type": "application/json",
  Accept: "application/json",
};

export class ApiClient {
  constructor(baseUrl = "/api/v1") {
    this.baseUrl = baseUrl;
  }

  async request(path, options = {}) {
    const response = await fetch(`${this.baseUrl}${path}`, {
      ...options,
      headers: {
        ...DEFAULT_HEADERS,
        ...(options.headers ?? {}),
      },
    });

    let payload = null;
    try {
      payload = await response.json();
    } catch (error) {
      payload = null;
    }

    if (!response.ok) {
      const error = new Error(payload?.error ?? "Request failed.");
      error.status = response.status;
      error.details = payload;
      throw error;
    }

    return payload;
  }

  async getSession() {
    return this.request("/me");
  }

  async getDaily(start, end) {
    return this.request(`/analytics/daily?start=${start}&end=${end}`);
  }

  async getMonthly(start, end) {
    return this.request(`/analytics/monthly?start=${start}&end=${end}`);
  }

  async getEngagement(start, end) {
    return this.request(`/analytics/engagement-scores?start=${start}&end=${end}`);
  }

  async getAlerts(start, end) {
    return this.request(`/analytics/alerts?start=${start}&end=${end}`);
  }
}
