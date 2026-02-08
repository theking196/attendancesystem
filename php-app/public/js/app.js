import { ApiClient } from "./apiClient.js";
import { Router } from "./router.js";
import { createAppShell } from "./components/layout.js";
import {
  createCard,
  createNotice,
  createPill,
  createTable,
  createTag,
} from "./components/ui.js";

const api = new ApiClient();
const appRoot = document.getElementById("app");

function formatDate(date) {
  return date.toISOString().slice(0, 10);
}

function dateRange(days) {
  const end = new Date();
  const start = new Date();
  start.setDate(end.getDate() - days);
  return {
    start: formatDate(start),
    end: formatDate(end),
  };
}

function monthRange(months) {
  const end = new Date();
  const start = new Date();
  start.setMonth(end.getMonth() - months);
  return {
    start: formatDate(start),
    end: formatDate(end),
  };
}

function clearContent(container) {
  container.innerHTML = "";
}

function setActive(navLinks, path) {
  navLinks.forEach((link, key) => {
    if (key === path) {
      link.classList.add("active");
    } else {
      link.classList.remove("active");
    }
  });
}

function renderSectionHeading(title, subtitle) {
  const wrapper = document.createElement("div");
  wrapper.className = "section-title";
  const h2 = document.createElement("h2");
  h2.textContent = title;
  const pill = createPill(subtitle);
  wrapper.append(h2, pill);
  return wrapper;
}

function renderAccessNotice(details) {
  const required =
    details?.rbac?.required_permissions?.join(", ") ??
    details?.rbac?.required_roles?.join(", ") ??
    "additional access";
  return createNotice(
    "Access restricted",
    `You need ${required} to view this section.`
  );
}

async function bootstrap() {
  let session;
  try {
    session = await api.getSession();
  } catch (error) {
    appRoot?.append(
      createNotice("Unable to load session", error.message ?? "Try again.")
    );
    return;
  }

  const role = session.data.role;
  const permissions = session.data.permissions;
  const permissionSet = new Set(permissions);

  const routes = [
    {
      path: "/",
      label: "Overview",
      permission: "analytics:read",
    },
    {
      path: "/engagement",
      label: "Engagement",
      permission: "alerts:read",
    },
    {
      path: "/alerts",
      label: "Alerts",
      permission: "alerts:read",
    },
  ].filter((route) => permissionSet.has(route.permission));

  const shell = createAppShell({ role, permissions, routes });
  appRoot?.appendChild(shell.root);

  const router = new Router();

  router.register("/", async () => {
    setActive(shell.navLinks, "/");
    clearContent(shell.content);

    const heading = renderSectionHeading(
      "Analytics Overview",
      "Last 7 days + 6 months"
    );
    shell.content.appendChild(heading);

    if (!permissionSet.has("analytics:read")) {
      shell.content.appendChild(
        createNotice(
          "Access required",
          "Your role does not include analytics access."
        )
      );
      return;
    }

    const { start, end } = dateRange(7);
    const { start: monthStart, end: monthEnd } = monthRange(6);

    try {
      const [daily, monthly] = await Promise.all([
        api.getDaily(start, end),
        api.getMonthly(monthStart, monthEnd),
      ]);

      const dailyTotal = daily.data.reduce(
        (sum, row) => sum + Number(row.total_attendance ?? 0),
        0
      );
      const monthlyAverage =
        monthly.data.reduce(
          (sum, row) => sum + Number(row.average_attendance ?? 0),
          0
        ) / Math.max(monthly.data.length, 1);

      const summaryGrid = document.createElement("div");
      summaryGrid.className = "grid";
      summaryGrid.append(
        createCard("Total Attendance", dailyTotal, `${start} → ${end}`),
        createCard(
          "Average Monthly Attendance",
          monthlyAverage.toFixed(1),
          `${monthStart} → ${monthEnd}`
        ),
        createCard(
          "Active Permissions",
          permissions.length,
          permissions.join(", ") || "None"
        )
      );

      shell.content.appendChild(summaryGrid);

      const dailyTable = createTable(
        ["Date", "Attendance", "Late", "Remote"],
        daily.data.map((row) => [
          row.date,
          row.total_attendance,
          row.late_arrivals,
          row.remote_attendance,
        ])
      );

      const dailyCard = document.createElement("div");
      dailyCard.className = "card";
      dailyCard.append(renderSectionHeading("Daily Metrics", "Week snapshot"));
      dailyCard.appendChild(dailyTable);

      const monthlyTable = createTable(
        ["Month", "Average Attendance", "Absences"],
        monthly.data.map((row) => [
          row.month,
          row.average_attendance,
          row.absences,
        ])
      );
      const monthlyCard = document.createElement("div");
      monthlyCard.className = "card";
      monthlyCard.append(
        renderSectionHeading("Monthly Trends", "6 month roll-up")
      );
      monthlyCard.appendChild(monthlyTable);

      shell.content.append(dailyCard, monthlyCard);
    } catch (error) {
      shell.content.appendChild(renderAccessNotice(error.details));
    }
  });

  router.register("/engagement", async () => {
    setActive(shell.navLinks, "/engagement");
    clearContent(shell.content);
    shell.content.appendChild(
      renderSectionHeading("Engagement Scores", "Manager view")
    );

    if (!permissionSet.has("alerts:read")) {
      shell.content.appendChild(renderAccessNotice());
      return;
    }

    const { start, end } = dateRange(30);
    try {
      const scores = await api.getEngagement(start, end);
      const scoreTable = createTable(
        ["Team", "Score", "Delta", "Status"],
        scores.data.map((row) => [
          row.team,
          row.score,
          row.delta,
          createTag(row.status),
        ])
      );
      const card = document.createElement("div");
      card.className = "card";
      card.appendChild(scoreTable);
      shell.content.appendChild(card);
    } catch (error) {
      shell.content.appendChild(renderAccessNotice(error.details));
    }
  });

  router.register("/alerts", async () => {
    setActive(shell.navLinks, "/alerts");
    clearContent(shell.content);
    shell.content.appendChild(renderSectionHeading("Alerts", "Escalations"));

    if (!permissionSet.has("alerts:read")) {
      shell.content.appendChild(renderAccessNotice());
      return;
    }

    const { start, end } = dateRange(14);
    try {
      const alerts = await api.getAlerts(start, end);
      const table = createTable(
        ["Date", "Type", "Severity", "Status"],
        alerts.data.map((row) => [
          row.date,
          row.type,
          createTag(row.severity),
          row.status,
        ])
      );
      const card = document.createElement("div");
      card.className = "card";
      card.appendChild(table);
      shell.content.appendChild(card);
    } catch (error) {
      shell.content.appendChild(renderAccessNotice(error.details));
    }
  });

  router.setNotFound((path) => {
    if (routes.length > 0) {
      router.navigate(routes[0].path);
      return;
    }

    clearContent(shell.content);
    shell.content.appendChild(
      createNotice("No accessible views", `No routes are available for ${path}.`)
    );
  });

  if (routes.length > 0 && window.location.hash === "") {
    router.navigate(routes[0].path);
  }

  router.start();
}

bootstrap();
