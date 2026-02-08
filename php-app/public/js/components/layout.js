export function createAppShell({ role, permissions, routes }) {
  const root = document.createElement("div");
  root.className = "app-shell";

  const header = document.createElement("header");
  header.className = "app-header";

  const titleWrap = document.createElement("div");
  const title = document.createElement("h1");
  title.className = "app-header__title";
  title.textContent = "Attendance Insights";
  const meta = document.createElement("div");
  meta.className = "app-header__meta";
  meta.innerHTML = `
    <span>Role: <strong>${role}</strong></span>
    <span>Permissions: ${permissions.join(", ") || "None"}</span>
  `;
  titleWrap.append(title, meta);

  const nav = document.createElement("nav");
  nav.className = "app-nav";
  const navLinks = new Map();
  routes.forEach((route) => {
    const link = document.createElement("a");
    link.href = `#${route.path}`;
    link.textContent = route.label;
    nav.appendChild(link);
    navLinks.set(route.path, link);
  });

  header.append(titleWrap, nav);
  const content = document.createElement("main");
  content.className = "app-content";

  root.append(header, content);

  return { root, content, navLinks };
}
