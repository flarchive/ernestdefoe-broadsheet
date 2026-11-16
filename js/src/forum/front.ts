import app from 'flarum/forum/app';

export interface Person {
  name: string;
  slug: string;
}

export interface Story {
  id: number;
  slug: string;
  /** Where the discussion opens for this reader: their first unread post. */
  near: number;
  title: string;
  excerpt: string;
  /** The lead's picture only; null for every other story. */
  image: string | null;
  author: Person | null;
  replies: number;
  lastReply: Person | null;
  createdAt: string | null;
  tag: { id: number; name: string; slug: string } | null;
}

export interface FrontData {
  lead: Story | null;
  columns: Story[];
  mostDiscussed: Story[];
}

/*
 * 🚨 Module-level, not component state: the hero is redrawn and remounted as
 * the reader moves between the front page and the rest of the forum, and the
 * data is fetched at most once a minute however often that happens.
 */
let front: FrontData | null = null;
let loadedAt = 0;
let pending: Promise<FrontData> | null = null;

/** The preloaded copy, taken once: a full page load of the front ships it. */
function preloaded(): FrontData | null {
  const data = (app.data as any).broadsheetFront;

  if (data) delete (app.data as any).broadsheetFront;

  return data || null;
}

export function getFront(): FrontData | null {
  if (!front) {
    front = preloaded();
    if (front) loadedAt = Date.now();
  }

  return front;
}

/**
 * The front page, fetched when there is none or what there is has gone a
 * minute stale (a reader coming back to it after a while sees today's paper).
 */
export function loadFront(): Promise<FrontData> {
  if (getFront() && Date.now() - loadedAt < 60_000) return Promise.resolve(front!);

  pending ||= app
    .request<FrontData>({ method: 'GET', url: app.forum.attribute('apiUrl') + '/broadsheet/front' })
    .then((data) => {
      front = data;
      loadedAt = Date.now();
      m.redraw();

      return data;
    })
    .finally(() => (pending = null));

  return pending;
}
