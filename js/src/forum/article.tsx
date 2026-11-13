import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import DiscussionHero from 'flarum/forum/components/DiscussionHero';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import humanTime from 'flarum/common/helpers/humanTime';
import username from 'flarum/common/helpers/username';

const t = (key: string, params?: any) => app.translator.trans(`ernestdefoe-broadsheet.forum.${key}`, params);

/** Reading time of the opening post, at 230 words a minute; null until it has loaded. */
function readingTime(discussion: any): number | null {
  const html = discussion.firstPost?.()?.contentHtml?.();
  if (typeof html !== 'string') return null;

  const words = html
    .replace(/<[^>]+>/g, ' ')
    .split(/\s+/)
    .filter(Boolean).length;

  return Math.max(1, Math.round(words / 230));
}

/**
 * A discussion page that reads like an article: the headline and its byline
 * at the top, the opening post with a drop cap (less/topic), and the replies
 * under a "Letters in reply" rule.
 */
export default function article() {
  extend(DiscussionHero.prototype, 'items', function (this: any, items) {
    const discussion = this.attrs.discussion;
    const user = discussion.user();
    const minutes = readingTime(discussion);
    const created = discussion.createdAt();

    items.add(
      'broadsheetByline',
      <div className="Broadsheet-articleByline">
        {user ? (
          <Link href={app.route.user(user)} className="Broadsheet-articleAvatar" tabindex="-1" aria-hidden="true">
            <Avatar user={user} />
          </Link>
        ) : null}
        <div className="Broadsheet-byline">
          {t('by', {
            name: user ? (
              <Link href={app.route.user(user)}>
                <b>{username(user)}</b>
              </Link>
            ) : (
              <b>{t('deleted_user')}</b>
            ),
          })}
          <br />
          {created ? humanTime(created) : null}
          {minutes ? [' · ', t('read_time', { count: minutes })] : null}
        </div>
      </div>,
      -10
    );
  });

  // 🚨 By module name: PostStream is a lazy chunk in Flarum 2, so a static
  // import is undefined when this runs.
  extend('flarum/forum/components/PostStream', 'afterFirstPostItems', function (this: any, items: any) {
    const replies = Math.max(0, (this.discussion.commentCount() || 1) - 1);
    if (!replies) return;

    items.add(
      'broadsheetLetters',
      <div className="Broadsheet-letters">
        <h2 className="Broadsheet-lettersTitle">{t('letters')}</h2>
        <span className="Broadsheet-byline">{t('replies', { count: replies })}</span>
      </div>,
      100
    );
  });
}
