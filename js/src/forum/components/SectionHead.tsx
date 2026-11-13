import Component from 'flarum/common/Component';

/**
 * A section's own head, on a tag page: its name set like a section front,
 * and its description. Replaces flarum/tags' coloured hero; the tag's colour
 * stays as the rule under the name.
 */
export default class SectionHead extends Component<{ model: any }> {
  view() {
    const tag = this.attrs.model;
    const color = tag.color();

    return (
      <div className="Broadsheet-sectionHead" style={color ? { '--bs-section': color } : undefined}>
        <h1 className="Broadsheet-sectionName">{tag.name()}</h1>
        {tag.description() ? <p className="Broadsheet-sectionText">{tag.description()}</p> : null}
      </div>
    );
  }
}
