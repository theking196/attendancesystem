export class Router {
  constructor() {
    this.routes = new Map();
    this.notFoundHandler = null;
  }

  register(path, handler) {
    this.routes.set(path, handler);
  }

  setNotFound(handler) {
    this.notFoundHandler = handler;
  }

  start() {
    window.addEventListener("hashchange", () => this.handleRoute());
    this.handleRoute();
  }

  navigate(path) {
    window.location.hash = path;
  }

  async handleRoute() {
    const path = window.location.hash.replace("#", "") || "/";
    const handler = this.routes.get(path);
    if (handler) {
      await handler();
      return;
    }

    if (this.notFoundHandler) {
      await this.notFoundHandler(path);
    }
  }
}
