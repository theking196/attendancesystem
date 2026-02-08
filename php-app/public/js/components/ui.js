export function createCard(title, value, subtitle) {
  const card = document.createElement("div");
  card.className = "card";
  const heading = document.createElement("h3");
  heading.textContent = title;
  const metric = document.createElement("div");
  metric.style.fontSize = "1.6rem";
  metric.style.fontWeight = "700";
  metric.textContent = value;
  const sub = document.createElement("div");
  sub.style.color = "#6b7280";
  sub.style.fontSize = "0.85rem";
  sub.textContent = subtitle;
  card.append(heading, metric, sub);
  return card;
}

export function createNotice(title, message) {
  const notice = document.createElement("div");
  notice.className = "notice";
  const heading = document.createElement("strong");
  heading.textContent = title;
  const body = document.createElement("span");
  body.textContent = message;
  notice.append(heading, body);
  return notice;
}

export function createTag(label) {
  const tag = document.createElement("span");
  tag.className = "tag";
  tag.textContent = label;
  return tag;
}

export function createPill(label) {
  const pill = document.createElement("span");
  pill.className = "pill";
  pill.textContent = label;
  return pill;
}

export function createTable(headers, rows) {
  const table = document.createElement("table");
  table.className = "table";
  const thead = document.createElement("thead");
  const headRow = document.createElement("tr");
  headers.forEach((header) => {
    const th = document.createElement("th");
    th.textContent = header;
    headRow.appendChild(th);
  });
  thead.appendChild(headRow);

  const tbody = document.createElement("tbody");
  rows.forEach((row) => {
    const tr = document.createElement("tr");
    row.forEach((cell) => {
      const td = document.createElement("td");
      if (cell instanceof HTMLElement) {
        td.appendChild(cell);
      } else {
        td.textContent = String(cell);
      }
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });

  table.append(thead, tbody);
  return table;
}
