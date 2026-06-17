(() => {
  const ROMAN_STATUSES = new Set(['core', 'direct_control', 'province', 'client_state', 'occupied']);
  const DRAW_ORDER = ['lost', 'contested', 'client_state', 'occupied', 'province', 'direct_control', 'core'];
  const MAP_BOUNDS = [[-11.8, 24.2], [47.5, 56.5]];
  const SVG_NS = 'http://www.w3.org/2000/svg';
  const REGION_REFERENCE_QUERIES = {
    latium: 'ローマ',
    central_italy: 'ラティウム',
    southern_italy: 'マグナ・グラエキア',
    cisalpine_gaul: 'ガリア・キサルピナ',
    sicily: 'シチリア属州',
    sardinia_corsica: 'サルディニア・コルシカ属州',
    hispania_coast: 'ヒスパニア属州',
    hispania_interior: 'ヒスパニア属州',
    africa_proconsularis: 'アフリカ属州',
    numidia: 'ヌミディア',
    mauretania: 'マウレタニア',
    macedonia_greece: 'マケドニア属州',
    asia_minor_west: 'アシア属州',
    anatolia_central: 'アナトリア ローマ帝国',
    syria: 'シリア属州',
    judea: 'ユダヤ属州',
    cyprus: 'キプロス ローマ帝国',
    gaul_narbonensis: 'ガリア・ナルボネンシス',
    gaul: 'ガリア戦争',
    egypt: 'エジプト属州',
    alpine_danube: 'ドナウ川 ローマ帝国',
    balkans_moesia: 'モエシア',
    thrace: 'トラキア属州',
    britannia: 'ブリタンニア',
    dacia: 'ダキア属州',
    arabia: 'アラビア・ペトラエア',
    mesopotamia: 'メソポタミア属州',
    armenia_client: 'アルメニア王国 ローマ帝国',
  };

  const els = {};
  const state = {
    data: null,
    map: null,
    mapReady: false,
    year: -753,
    selectedRegionId: null,
    selectedEventKey: null,
    timer: null,
    showLost: true,
    visibleRegions: [],
    renderFrame: null,
  };

  document.addEventListener('DOMContentLoaded', init);

  async function init() {
    cacheElements();
    bindEvents();

    try {
      const response = await fetch(window.ROME_DATA_URL || 'api/territory.php', { cache: 'no-store' });

      if (!response.ok) {
        throw new Error(`Data request failed: ${response.status}`);
      }

      state.data = await response.json();
      state.data.events.sort((a, b) => yearToIndex(a.year) - yearToIndex(b.year));

      configureRange();
      renderLegend();
      initMap();
      setYear(initialYear());
    } catch (error) {
      showMapMessage(`データを読み込めませんでした: ${error.message}`);
      renderLoadError(error);
    }
  }

  function cacheElements() {
    els.map = document.getElementById('map');
    els.overlay = document.getElementById('territoryOverlay');
    els.mapMessage = document.getElementById('mapMessage');
    els.yearSlider = document.getElementById('yearSlider');
    els.yearLabel = document.getElementById('yearLabel');
    els.summaryYear = document.getElementById('summaryYear');
    els.eraChip = document.getElementById('eraChip');
    els.playButton = document.getElementById('playButton');
    els.playIcon = document.getElementById('playIcon');
    els.prevEventButton = document.getElementById('prevEventButton');
    els.nextEventButton = document.getElementById('nextEventButton');
    els.speedSelect = document.getElementById('speedSelect');
    els.lostToggle = document.getElementById('lostToggle');
    els.legendList = document.getElementById('legendList');
    els.eventList = document.getElementById('eventList');
    els.eventCount = document.getElementById('eventCount');
    els.regionList = document.getElementById('regionList');
    els.regionDetail = document.getElementById('regionDetail');
    els.selectedRegionBadge = document.getElementById('selectedRegionBadge');
    els.controlledCount = document.getElementById('controlledCount');
    els.contestedCount = document.getElementById('contestedCount');
    els.lostCount = document.getElementById('lostCount');
  }

  function bindEvents() {
    els.yearSlider.addEventListener('input', () => {
      stopPlayback();
      state.selectedEventKey = null;
      setYear(indexToYear(Number(els.yearSlider.value)));
    });

    els.playButton.addEventListener('click', togglePlayback);
    els.prevEventButton.addEventListener('click', () => jumpToEvent(-1));
    els.nextEventButton.addEventListener('click', () => jumpToEvent(1));
    els.lostToggle.addEventListener('change', () => {
      state.showLost = els.lostToggle.checked;
      renderYear();
    });
  }

  function initMap() {
    if (!window.maplibregl) {
      showMapMessage('MapLibreを読み込めませんでした。ネットワーク接続を確認してください。');
      return;
    }

    state.map = new maplibregl.Map({
      container: els.map,
      style: window.ROME_MAP_STYLE || 'https://tiles.openfreemap.org/styles/liberty',
      center: [18.5, 39.2],
      zoom: 3.25,
      minZoom: 2,
      maxZoom: 8,
      attributionControl: true,
    });

    state.map.addControl(new maplibregl.NavigationControl({ visualizePitch: false }), 'top-left');
    state.map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');

    state.map.on('load', installMapLayers);
    state.map.on('style.load', installMapLayers);
    state.map.on('move', scheduleMapRender);
    state.map.on('zoom', scheduleMapRender);
    state.map.on('resize', scheduleMapRender);

    state.map.on('error', () => {
      showMapMessage('地図タイルの読み込みに失敗しました。OpenFreeMapへ接続できるか確認してください。');
    });
  }

  function installMapLayers() {
    if (state.mapReady) {
      return;
    }

    try {
      state.mapReady = true;
      renderYear();
      state.map.fitBounds(MAP_BOUNDS, { padding: 28, duration: 0 });
      scheduleMapRender();
    } catch (error) {
      showMapMessage(`領域レイヤーを追加できませんでした: ${error.message}`);
    }
  }

  function configureRange() {
    const { start, end } = state.data.meta.range;
    els.yearSlider.min = String(yearToIndex(start));
    els.yearSlider.max = String(yearToIndex(end));
    els.yearSlider.value = String(yearToIndex(start));
  }

  function renderLegend() {
    clearNode(els.legendList);

    Object.entries(state.data.statuses).forEach(([status, info]) => {
      const item = document.createElement('div');
      item.className = 'legend-item';

      const swatch = document.createElement('span');
      swatch.className = 'legend-swatch';
      swatch.style.background = info.color;

      if (status === 'lost') {
        swatch.classList.add('is-hatched');
      }

      const label = document.createElement('span');
      label.textContent = info.label;

      item.append(swatch, label);
      els.legendList.appendChild(item);
    });
  }

  function setYear(year) {
    state.year = clampYear(year);
    els.yearSlider.value = String(yearToIndex(state.year));
    renderYear();
  }

  function renderYear() {
    if (!state.data) {
      return;
    }

    const activeRegions = getActiveRegions(state.year);
    const visibleRegions = activeRegions
      .filter((region) => state.showLost || region.activeControl.status !== 'lost')
      .sort((a, b) => DRAW_ORDER.indexOf(a.activeControl.status) - DRAW_ORDER.indexOf(b.activeControl.status));

    if (!state.selectedRegionId || !visibleRegions.some((region) => region.id === state.selectedRegionId)) {
      const firstControlled = visibleRegions.find((region) => region.id === 'latium')
        || visibleRegions.find((region) => region.activeControl.status === 'core')
        || visibleRegions.find((region) => ROMAN_STATUSES.has(region.activeControl.status));
      state.selectedRegionId = firstControlled?.id || visibleRegions[0]?.id || null;
    }

    state.visibleRegions = visibleRegions;

    updateYearText();
    renderMapLayers();
    renderEvents();
    renderRegionList(activeRegions);
    renderRegionDetail(activeRegions);
    renderMetrics(activeRegions);
  }

  function renderMapLayers() {
    if (!state.mapReady || !els.overlay) {
      return;
    }

    const canvas = state.map.getCanvas();
    const width = canvas.clientWidth || els.map.clientWidth;
    const height = canvas.clientHeight || els.map.clientHeight;
    const selectedPaths = [];

    els.overlay.setAttribute('viewBox', `0 0 ${width} ${height}`);
    els.overlay.setAttribute('width', String(width));
    els.overlay.setAttribute('height', String(height));
    clearNode(els.overlay);
    appendOverlayDefs();

    state.visibleRegions.forEach((region) => {
      const control = region.activeControl;
      const status = statusInfo(control.status);

      region.polygons.forEach((polygon) => {
        const pathData = projectedPath(polygon);
        const shape = createSvgElement('path');
        shape.setAttribute('class', `territory-shape status-${control.status}`);
        shape.setAttribute('d', pathData);
        shape.setAttribute('fill', status.color);
        shape.setAttribute('fill-opacity', String(fillOpacity(control.status)));
        shape.dataset.regionId = region.id;
        shape.setAttribute('aria-label', `${region.label} ${status.label}`);
        shape.addEventListener('click', () => selectRegion(region.id));
        els.overlay.appendChild(shape);

        if (control.status === 'lost') {
          const hatch = createSvgElement('path');
          hatch.setAttribute('class', 'territory-hatch');
          hatch.setAttribute('d', pathData);
          els.overlay.appendChild(hatch);
        }

        if (region.id === state.selectedRegionId) {
          selectedPaths.push(pathData);
        }
      });
    });

    selectedPaths.forEach((pathData) => {
      const outline = createSvgElement('path');
      outline.setAttribute('class', 'territory-selected-outline');
      outline.setAttribute('d', pathData);
      els.overlay.appendChild(outline);
    });

    renderOverlayLabels();
  }

  function scheduleMapRender() {
    if (!state.mapReady || state.renderFrame) {
      return;
    }

    state.renderFrame = window.requestAnimationFrame(() => {
      state.renderFrame = null;
      renderMapLayers();
    });
  }

  function appendOverlayDefs() {
    const defs = createSvgElement('defs');
    const pattern = createSvgElement('pattern');
    const stripe = createSvgElement('path');

    pattern.setAttribute('id', 'lostHatch');
    pattern.setAttribute('width', '12');
    pattern.setAttribute('height', '12');
    pattern.setAttribute('patternUnits', 'userSpaceOnUse');
    pattern.setAttribute('patternTransform', 'rotate(45)');
    stripe.setAttribute('d', 'M 0 0 L 0 12');
    stripe.setAttribute('stroke', 'rgba(255,255,255,.52)');
    stripe.setAttribute('stroke-width', '3');
    pattern.appendChild(stripe);
    defs.appendChild(pattern);
    els.overlay.appendChild(defs);
  }

  function renderOverlayLabels() {
    const labels = toLabelFeatureCollection(state.visibleRegions).features;

    labels.forEach((feature) => {
      const point = state.map.project(feature.geometry.coordinates);
      const label = createSvgElement('text');
      label.setAttribute('class', 'territory-label');
      label.setAttribute('x', String(point.x));
      label.setAttribute('y', String(point.y));
      label.textContent = feature.properties.name;
      els.overlay.appendChild(label);
    });
  }

  function projectedPath(points) {
    return closedRing(points)
      .map((point, index) => {
        const projected = state.map.project(point);
        return `${index === 0 ? 'M' : 'L'} ${projected.x.toFixed(1)} ${projected.y.toFixed(1)}`;
      })
      .join(' ')
      .concat(' Z');
  }

  function fillOpacity(status) {
    if (status === 'lost') {
      return 0.44;
    }

    if (status === 'contested') {
      return 0.62;
    }

    if (status === 'occupied') {
      return 0.68;
    }

    return 0.74;
  }

  function createSvgElement(name) {
    return document.createElementNS(SVG_NS, name);
  }

  function toLabelFeatureCollection(regions) {
    const excluded = new Set(['judea', 'cyprus', 'sicily', 'latium']);
    const features = regions
      .filter((region) => ROMAN_STATUSES.has(region.activeControl.status))
      .filter((region) => !excluded.has(region.id))
      .slice(0, 22)
      .map((region) => ({
        type: 'Feature',
        properties: {
          id: region.id,
          name: region.label,
        },
        geometry: {
          type: 'Point',
          coordinates: polygonCenter(region.polygons[0]),
        },
      }));

    return {
      type: 'FeatureCollection',
      features,
    };
  }

  function renderEvents() {
    const events = state.data.events;
    const currentIndex = yearToIndex(state.year);
    const exactEvents = events.filter((event) => yearToIndex(event.year) === currentIndex);
    const lastPastIndex = findLastEventIndex(currentIndex);
    const start = Math.max(0, lastPastIndex - 2);
    const eventWindow = events.slice(start, start + 6);
    const items = exactEvents.length > 0 ? mergeEvents(eventWindow, exactEvents) : eventWindow;

    if (!state.selectedEventKey || !items.some((event) => eventKey(event) === state.selectedEventKey)) {
      state.selectedEventKey = exactEvents[0] ? eventKey(exactEvents[0]) : null;
    }

    clearNode(els.eventList);
    els.eventCount.textContent = exactEvents.length > 0 ? `${exactEvents.length}件` : '直近';

    items.forEach((event) => {
      const item = document.createElement('article');
      item.className = 'event-item';
      const key = eventKey(event);
      const isSelected = key === state.selectedEventKey;

      if (yearToIndex(event.year) === currentIndex) {
        item.classList.add('is-current');
      }

      if (isSelected) {
        item.classList.add('is-expanded');
      }

      const main = document.createElement('button');
      main.type = 'button';
      main.className = 'event-main';
      main.addEventListener('click', () => selectEvent(event));

      const year = document.createElement('span');
      year.className = 'event-year';
      year.textContent = formatYear(event.year);

      const title = document.createElement('span');
      title.className = 'event-title';
      title.textContent = event.title;

      const body = document.createElement('span');
      body.className = 'event-body';
      body.textContent = event.body;

      main.append(year, title);
      item.appendChild(main);

      if (isSelected) {
        item.append(body, createReferenceLinks(eventSearchQuery(event)));
      }

      els.eventList.appendChild(item);
    });
  }

  function selectEvent(event) {
    state.selectedEventKey = eventKey(event);
    stopPlayback();
    setYear(event.year);
  }

  function eventKey(event) {
    return `${event.year}:${event.title}`;
  }

  function eventSearchQuery(event) {
    return `${event.title} ローマ史`;
  }

  function regionSearchQuery(region) {
    return REGION_REFERENCE_QUERIES[region.id] || `${region.label} ${region.name}`;
  }

  function createReferenceLinks(query) {
    const links = document.createElement('div');
    links.className = 'reference-links';

    links.append(
      createReferenceLink('Wikipedia', `https://ja.wikipedia.org/wiki/Special:Search?search=${encodeURIComponent(query)}`),
      createReferenceLink('辞書検索', `https://kotobank.jp/search?q=${encodeURIComponent(query)}`),
    );

    return links;
  }

  function createReferenceLink(label, href) {
    const link = document.createElement('a');
    link.className = 'reference-link';
    link.href = href;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.textContent = label;
    link.addEventListener('click', (event) => event.stopPropagation());
    return link;
  }

  function renderRegionList(activeRegions) {
    clearNode(els.regionList);

    const displayRegions = activeRegions
      .filter((region) => state.showLost || region.activeControl.status !== 'lost')
      .sort((a, b) => {
        const order = DRAW_ORDER.indexOf(b.activeControl.status) - DRAW_ORDER.indexOf(a.activeControl.status);
        return order || a.label.localeCompare(b.label, 'ja');
      });

    if (displayRegions.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'empty-state';
      empty.textContent = '表示中の領域はありません。';
      els.regionList.appendChild(empty);
      return;
    }

    displayRegions.forEach((region) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'region-item';

      if (region.id === state.selectedRegionId) {
        item.classList.add('is-selected');
      }

      item.addEventListener('click', () => selectRegion(region.id, true));

      const dot = document.createElement('span');
      dot.className = 'region-dot';
      dot.style.background = statusInfo(region.activeControl.status).color;

      const name = document.createElement('span');
      name.className = 'region-name';
      name.textContent = region.label;

      const status = document.createElement('span');
      status.className = 'region-status';
      status.textContent = statusInfo(region.activeControl.status).label;

      item.append(dot, name, status);
      els.regionList.appendChild(item);
    });
  }

  function renderRegionDetail(activeRegions) {
    clearNode(els.regionDetail);

    const selected = activeRegions.find((region) => region.id === state.selectedRegionId);

    if (!selected || (!state.showLost && selected.activeControl.status === 'lost')) {
      els.selectedRegionBadge.textContent = '未選択';
      const empty = document.createElement('p');
      empty.className = 'empty-state';
      empty.textContent = '選択中の領域はありません。';
      els.regionDetail.appendChild(empty);
      return;
    }

    const status = statusInfo(selected.activeControl.status);
    els.selectedRegionBadge.textContent = status.label;

    const name = document.createElement('strong');
    name.textContent = selected.label;

    const controller = document.createElement('p');
    controller.className = 'region-controller';
    controller.textContent = selected.activeControl.controller;

    const meta = document.createElement('div');
    meta.className = 'region-meta';

    const badge = document.createElement('span');
    badge.className = 'region-status';
    badge.style.borderColor = status.color;
    badge.textContent = status.label;

    const confidence = document.createElement('span');
    confidence.className = 'confidence';
    confidence.textContent = `信頼度 ${selected.activeControl.confidence || 'unknown'}`;

    meta.append(badge, confidence);
    els.regionDetail.append(name, controller, meta);

    const description = document.createElement('p');
    description.className = 'region-note';
    description.textContent = `${formatYear(state.year)}時点では「${selected.activeControl.controller}」の${status.label}として扱っています。地図上の境界はシミュレーション用の概略です。`;
    els.regionDetail.appendChild(description);

    if (selected.activeControl.note) {
      const note = document.createElement('p');
      note.className = 'region-note';
      note.textContent = selected.activeControl.note;
      els.regionDetail.appendChild(note);
    }

    els.regionDetail.appendChild(createReferenceLinks(regionSearchQuery(selected)));
  }

  function renderMetrics(activeRegions) {
    const controlled = activeRegions.filter((region) => ROMAN_STATUSES.has(region.activeControl.status)).length;
    const contested = activeRegions.filter((region) => region.activeControl.status === 'contested').length;
    const lost = activeRegions.filter((region) => region.activeControl.status === 'lost').length;

    els.controlledCount.textContent = String(controlled);
    els.contestedCount.textContent = String(contested);
    els.lostCount.textContent = String(lost);
  }

  function selectRegion(regionId, shouldFit = false) {
    state.selectedRegionId = regionId;
    renderYear();

    if (shouldFit) {
      fitRegion(regionId);
    }
  }

  function fitRegion(regionId) {
    if (!state.mapReady) {
      return;
    }

    const region = getActiveRegions(state.year).find((item) => item.id === regionId);

    if (!region) {
      return;
    }

    const bounds = regionBounds(region);
    state.map.fitBounds(bounds, {
      padding: { top: 70, bottom: 70, left: 70, right: 70 },
      maxZoom: 5.6,
      duration: 500,
    });
  }

  function updateYearText() {
    const formatted = formatYear(state.year);
    els.yearLabel.textContent = formatted;
    els.summaryYear.textContent = formatted;
    els.eraChip.textContent = eraName(state.year);
  }

  function togglePlayback() {
    if (state.timer) {
      stopPlayback();
      return;
    }

    startPlayback();
  }

  function startPlayback() {
    stopPlayback();
    els.playIcon.innerHTML = '&#10074;&#10074;';
    els.playButton.setAttribute('aria-label', '一時停止');
    els.playButton.setAttribute('title', '一時停止');

    state.timer = window.setInterval(() => {
      const nextIndex = Number(els.yearSlider.value) + Number(els.speedSelect.value);
      const maxIndex = Number(els.yearSlider.max);

      if (nextIndex >= maxIndex) {
        setYear(indexToYear(maxIndex));
        stopPlayback();
        return;
      }

      setYear(indexToYear(nextIndex));
    }, 140);
  }

  function stopPlayback() {
    if (state.timer) {
      window.clearInterval(state.timer);
      state.timer = null;
    }

    els.playIcon.innerHTML = '&#9654;';
    els.playButton.setAttribute('aria-label', '再生');
    els.playButton.setAttribute('title', '再生');
  }

  function jumpToEvent(direction) {
    stopPlayback();

    const currentIndex = yearToIndex(state.year);
    const events = state.data.events;
    const target = direction > 0
      ? events.find((event) => yearToIndex(event.year) > currentIndex)
      : [...events].reverse().find((event) => yearToIndex(event.year) < currentIndex);

    if (target) {
      selectEvent(target);
    }
  }

  function getActiveRegions(year) {
    return state.data.regions
      .map((region) => ({
        ...region,
        activeControl: getActiveControl(region, year),
      }))
      .filter((region) => region.activeControl);
  }

  function getActiveControl(region, year) {
    const current = yearToIndex(year);

    return region.control.find((control) => {
      const start = yearToIndex(control.start);
      const end = yearToIndex(control.end ?? state.data.meta.range.end);
      return current >= start && current <= end;
    }) || null;
  }

  function statusInfo(status) {
    return state.data.statuses[status] || { label: status, color: '#cccccc' };
  }

  function regionBounds(region) {
    const bounds = new maplibregl.LngLatBounds();
    region.polygons.flat().forEach((point) => bounds.extend(point));
    return bounds;
  }

  function closedRing(points) {
    const ring = points.map((point) => [Number(point[0]), Number(point[1])]);
    const first = ring[0];
    const last = ring[ring.length - 1];

    if (first[0] !== last[0] || first[1] !== last[1]) {
      ring.push([...first]);
    }

    return ring;
  }

  function polygonCenter(points) {
    const total = points.reduce((acc, point) => ({
      lon: acc.lon + point[0],
      lat: acc.lat + point[1],
    }), { lon: 0, lat: 0 });

    return [
      total.lon / points.length,
      total.lat / points.length,
    ];
  }

  function clearNode(node) {
    while (node.firstChild) {
      node.removeChild(node.firstChild);
    }
  }

  function showMapMessage(text) {
    els.mapMessage.textContent = text;
    els.mapMessage.hidden = false;
  }

  function findLastEventIndex(currentIndex) {
    let index = 0;

    state.data.events.forEach((event, eventIndex) => {
      if (yearToIndex(event.year) <= currentIndex) {
        index = eventIndex;
      }
    });

    return index;
  }

  function mergeEvents(windowEvents, exactEvents) {
    const eventMap = new Map();

    [...windowEvents, ...exactEvents].forEach((event) => {
      eventMap.set(`${event.year}-${event.title}`, event);
    });

    return [...eventMap.values()]
      .sort((a, b) => yearToIndex(a.year) - yearToIndex(b.year))
      .slice(0, 7);
  }

  function clampYear(year) {
    const { start, end } = state.data.meta.range;
    const index = Math.min(Math.max(yearToIndex(year), yearToIndex(start)), yearToIndex(end));
    return indexToYear(index);
  }

  function initialYear() {
    const params = new URLSearchParams(window.location.search);
    const requestedYear = Number.parseInt(params.get('year') || '', 10);

    if (Number.isInteger(requestedYear) && requestedYear !== 0) {
      return requestedYear;
    }

    return state.data.meta.range.start;
  }

  function yearToIndex(year) {
    return year < 0 ? year + 753 : year + 752;
  }

  function indexToYear(index) {
    return index < 753 ? -753 + index : index - 752;
  }

  function formatYear(year) {
    return year < 0 ? `紀元前${Math.abs(year)}年` : `西暦${year}年`;
  }

  function eraName(year) {
    if (year <= -510) {
      return '王政ローマ';
    }

    if (year <= -28) {
      return '共和政ローマ';
    }

    if (year <= 284) {
      return '帝政前期';
    }

    if (year <= 394) {
      return '帝政後期';
    }

    return '東西分裂期';
  }

  function renderLoadError(error) {
    clearNode(els.eventList);
    const message = document.createElement('p');
    message.className = 'empty-state';
    message.textContent = `データを読み込めませんでした: ${error.message}`;
    els.eventList.appendChild(message);
  }
})();
