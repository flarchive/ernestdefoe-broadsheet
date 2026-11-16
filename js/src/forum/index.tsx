import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import BroadsheetHero from './components/BroadsheetHero';
import header from './header';
import article from './article';

const t = (key: string) => app.translator.trans(`ernestdefoe-broadsheet.forum.${key}`);

/**
 * Is this the front page: the unfiltered discussion list's first page, the
 * one the lead story and the columns belong to?
 */
export function isFront(page: any): boolean {
  const route = page?.attrs?.routeName;
  const params = app.search.state.params();

  return (route === 'index' || route === 'default') && !params.q && !(app as any).currentTag?.() && !(Number(m.route.param('page')) > 1);
}

app.initializers.add(
  'ernestdefoe-broadsheet',
  () => {
    // After flarum/tags (which puts its TagHero here): the section's own
    // head replaces it, and the front page replaces the welcome hero.
    override(IndexPage.prototype, 'hero', function (this: any) {
      return <BroadsheetHero front={isFront(this)} section={(app as any).currentTag?.()} />;
    });

    // "The rest of the paper": core's list, under its own rule.
    extend(IndexPage.prototype, 'contentItems', function (this: any, items) {
      if (!isFront(this)) return;

      items.add('broadsheetRest', <h2 className="Broadsheet-rubric Broadsheet-rest">{t('rest_of_paper')}</h2>, 110);
    });

    header();
    article();
  },
  // Last, so the hero is ours whatever flarum/tags (or another extension)
  // put in that slot first.
  -100
);
