import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';
import Button from 'flarum/common/components/Button';
import ShareModal from './components/ShareModal';

app.initializers.add('framio-social-share', () => {
  extend(CommentPost.prototype, 'actionItems', function (items) {
    if (!app.session.user || !app.session.user.isAdmin()) return;

    const content = this.attrs.post.contentHtml();
    // İlk img etiketini yakala
    const imgRegex = /<img.*?src="(.*?)"/;
    const match = imgRegex.exec(content);

    if (match && match[1]) {
      items.add('framio-share', Button.component({
        icon: 'fab fa-facebook', // FontAwesome ikonu
        className: 'Button Button--link',
        onclick: () => app.modal.show(ShareModal, { 
            post: this.attrs.post, 
            imageUrl: match[1] 
        }),
      }, 'Meta\'da Paylaş'));
    }
  });
});