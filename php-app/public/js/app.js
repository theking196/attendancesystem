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

function renderRequestError(error) {
  if (error?.details?.rbac) {
    return renderAccessNotice(error.details);
  }

  return createNotice(
    "Unable to load data",
    error?.message ?? "Please try again later."
  );
}

function renderEmptyState(message) {
  return createNotice("No data available", message);
}

function getMetric(metrics, key) {
  return Number(metrics?.[key] ?? 0);
}

function formatAlertDetails(details) {
  if (!details) {
    return "No additional details.";
  }
  if (typeof details === "string") {
    return details;
  }
  if (details.late_count !== undefined) {
    return `Late count: ${details.late_count} in ${details.window_days} days.`;
  }
  if (details.drop_points !== undefined) {
    return `Drop: ${details.drop_points} pts over ${details.window_days} days.`;
  }
  return JSON.stringify(details);
}

function scoreStatus(score) {
  if (score >= 90) {
    return createTag("Excellent");
  }
  if (score >= 75) {
    return createTag("Steady");
  }
  return createTag("At risk");
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

      const dailyMetrics = daily.data ?? [];
      const monthlyMetrics = monthly.data ?? [];

      const dailyTotal = dailyMetrics.reduce((sum, row) => {
        const present = getMetric(row.metrics, "present_count");
        const late = getMetric(row.metrics, "late_count");
        return sum + present + late;
      }, 0);
      const monthlyAverage =
        monthlyMetrics.reduce((sum, row) => {
          const present = getMetric(row.metrics, "present_count");
          const late = getMetric(row.metrics, "late_count");
          return sum + present + late;
        }, 0) / Math.max(monthlyMetrics.length, 1);

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

      if (dailyMetrics.length === 0) {
        shell.content.appendChild(
          renderEmptyState("No daily analytics were returned for this window.")
        );
      } else {
        const dailyTable = createTable(
          ["Date", "Present", "Late", "Absent", "Unique users"],
          dailyMetrics.map((row) => [
            row.period_start,
            getMetric(row.metrics, "present_count"),
            getMetric(row.metrics, "late_count"),
            getMetric(row.metrics, "absent_count"),
            getMetric(row.metrics, "unique_users"),
          ])
        );

        const dailyCard = document.createElement("div");
        dailyCard.className = "card";
        dailyCard.append(renderSectionHeading("Daily Metrics", "Week snapshot"));
        dailyCard.appendChild(dailyTable);
        shell.content.appendChild(dailyCard);
      }

      if (monthlyMetrics.length === 0) {
        shell.content.appendChild(
          renderEmptyState("No monthly rollups were returned for this window.")
        );
      } else {
        const monthlyTable = createTable(
          ["Month", "Total logs", "Present", "Late", "Absent"],
          monthlyMetrics.map((row) => [
            row.period_start,
            getMetric(row.metrics, "total_logs"),
            getMetric(row.metrics, "present_count"),
            getMetric(row.metrics, "late_count"),
            getMetric(row.metrics, "absent_count"),
          ])
        );
        const monthlyCard = document.createElement("div");
        monthlyCard.className = "card";
        monthlyCard.append(
          renderSectionHeading("Monthly Trends", "6 month roll-up")
        );
        monthlyCard.appendChild(monthlyTable);
        shell.content.appendChild(monthlyCard);
      }
    } catch (error) {
      shell.content.appendChild(renderRequestError(error));
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
      const scoreRows = scores.data ?? [];
      if (scoreRows.length === 0) {
        shell.content.appendChild(
          renderEmptyState("No engagement scores were returned for this window.")
        );
        return;
      }

      const scoreTable = createTable(
        ["User", "Score", "Attended", "Total", "Status"],
        scoreRows.map((row) => [
          row.user_id,
          row.score,
          row.attended_count,
          row.total_count,
          scoreStatus(Number(row.score)),
        ])
      );
      const card = document.createElement("div");
      card.className = "card";
      card.appendChild(scoreTable);
      shell.content.appendChild(card);
    } catch (error) {
      shell.content.appendChild(renderRequestError(error));
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
      const alertRows = alerts.data ?? [];
      if (alertRows.length === 0) {
        shell.content.appendChild(
          renderEmptyState("No alerts were returned for this window.")
        );
        return;
      }

      const table = createTable(
        ["Date", "Type", "Severity", "Details"],
        alertRows.map((row) => [
          row.period_start,
          row.alert_type,
          createTag(row.severity),
          formatAlertDetails(row.details),
        ])
      );
      const card = document.createElement("div");
      card.className = "card";
      card.appendChild(table);
      shell.content.appendChild(card);
    } catch (error) {
      shell.content.appendChild(renderRequestError(error));
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
