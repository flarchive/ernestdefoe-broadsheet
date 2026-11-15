import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import extractText from 'flarum/common/utils/extractText';
import humanTime from 'flarum/common/helpers/humanTime';
import type Mithril from 'mithril';
import { getFront, loadFront, Person, Story } from '../front';
import { sectionUrl } from '../sections';

const t = (key: string, params?: any) => app.translator.trans(`ernestdefoe-broadsheet.forum.${key}`, params);

/** Where a story opens: the reader's first unread post, like the discussion list. */
function storyUrl(story: Story): string {
  return story.near > 1 ? app.route('discussion.near', { id: story.slug, near: story.near }) : app.route('discussion', { id: story.slug });
}

function person(user: Person | null): Mithril.Children {
  if (!user) return <b>{t('deleted_user')}</b>;

  return (
    <Link href={app.route('user', { username: user.slug })}>
      <b>{user.name}</b>
    </Link>
  );
}

function kicker(story: Story, extra?: Mithril.Children): Mithril.Children {
  if (!story.tag && !extra) return null;

  return (
    <div className="Broadsheet-kicker">
      {story.tag ? <Link href={sectionUrl(story.tag)}>{story.tag.name}</Link> : null}
      {story.tag && extra ? ' · ' : null}
      {extra}
    </div>
  );
}

function byline(story: Story, replies = true): Mithril.Children {
  return (
    <div className="Broadsheet-byline">
      {t('by', { name: person(story.author) })}
      {story.createdAt ? [' · ', humanTime(new Date(story.createdAt))] : null}
      {replies ? [' · ', t('replies', { count: story.replies })] : null}
    </div>
  );
}

/**
 * The front page: the lead story with the week's most discussed beside it,
 * then three stories in ruled columns.
 *
 * Drawn above core's discussion list, which stays below it as "the rest of
 * the paper": sorting, the sidebar and every extension's list items work as
 * they always do.
 */
export default class Front extends Component {
  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);

    loadFront().catch(() => {});
  }

  view() {
    const front = getFront();

    if (!front || (!front.lead && !front.columns.length)) return null;

    return (
      <section className="Broadsheet-front" aria-label={extractText(t('front_page'))}>
        <div className="Broadsheet-top">
          {front.lead ? this.lead(front.lead) : null}
          {front.mostDiscussed.length ? this.most(front.mostDiscussed) : null}
        </div>
        {front.columns.length ? <div className="Broadsheet-columns">{front.columns.map((story) => this.column(story))}</div> : null}
      </section>
    );
  }

  lead(story: Story) {
    return (
      <article className={'Broadsheet-lead' + (story.image ? ' Broadsheet-lead--picture' : '')}>
        {story.image ? (
          <figure className="Broadsheet-leadPicture">
            <Link href={storyUrl(story)} tabindex="-1" aria-hidden="true">
              <img src={story.image} alt="" loading="eager" decoding="async" />
            </Link>
          </figure>
        ) : null}
        {kicker(story, t('lead_label'))}
        <h2 className="Broadsheet-leadTitle">
          <Link href={storyUrl(story)}>{story.title}</Link>
        </h2>
        <div className="Broadsheet-leadText">
          {story.excerpt ? <p className="Broadsheet-standfirst">{story.excerpt}</p> : null}
          {byline(story, false)}
          <div className="Broadsheet-byline">
            {t('replies', { count: story.replies })}
            {story.lastReply ? [' · ', t('last_from', { name: person(story.lastReply) })] : null}
          </div>
        </div>
      </article>
    );
  }

  most(stories: Story[]) {
    return (
      <aside className="Broadsheet-most">
        <h2 className="Broadsheet-rubric">{t('most_discussed')}</h2>
        <ol className="Broadsheet-mostList">
          {stories.map((story, i) => (
            <li key={story.id}>
              <span className="Broadsheet-numeral" aria-hidden="true">
                {i + 1}
              </span>
              <div>
                <Link className="Broadsheet-mostTitle" href={storyUrl(story)}>
                  {story.title}
                </Link>
                <div className="Broadsheet-byline">
                  {story.tag ? [story.tag.name, ' · '] : null}
                  {t('replies', { count: story.replies })}
                </div>
              </div>
            </li>
          ))}
        </ol>
      </aside>
    );
  }

  column(story: Story) {
    return (
      <article className="Broadsheet-column" key={story.id}>
        {kicker(story)}
        <h3 className="Broadsheet-columnTitle">
          <Link href={storyUrl(story)}>{story.title}</Link>
        </h3>
        {story.excerpt ? <p className="Broadsheet-columnText">{story.excerpt}</p> : null}
        {byline(story)}
      </article>
    );
  }
}
