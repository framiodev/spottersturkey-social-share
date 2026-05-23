import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';

export default class ShareModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    
    const accountsJson = app.forum.attribute('framio-social.accounts_json') || '[]';
    this.accounts = [];
    try {
        this.accounts = JSON.parse(accountsJson);
    } catch (e) {
        console.error("JSON Parse Hatası", e);
    }

    this.selectedAccountIndex = Stream(0);
    this.scheduleTime = Stream(''); 
    
    // HTML taglerini temizle
    let cleanText = this.attrs.post.contentHtml().replace(/<[^>]*>?/gm, '').trim();
    this.message = Stream(cleanText);
    this.imageUrl = this.attrs.imageUrl;
    this.loading = false;
  }

  className() {
    return 'FramioShareModal Modal--large';
  }

  title() {
    return 'Meta Paylaşım Paneli';
  }

  content() {
    return (
      <div className="Modal-body">
        
        {/* Hesap Seçimi */}
        <div className="Form-group">
            <label>Hesap Seçin</label>
            <select className="FormControl" onchange={m.withAttr('value', this.selectedAccountIndex)} value={this.selectedAccountIndex()}>
                {this.accounts.map((acc, index) => (
                    <option value={index}>{acc.name}</option>
                ))}
            </select>
        </div>

        {/* Görsel */}
        <div className="Form-group" style="text-align:center; background:#eee; padding:10px;">
            <img src={this.imageUrl} style="max-height: 150px; border-radius: 4px;" />
        </div>

        {/* Metin */}
        <div className="Form-group">
          <label>Gönderi Metni</label>
          <textarea className="FormControl" rows="4" bidi={this.message} />
        </div>

        {/* Zamanlama */}
        <div className="Form-group">
            <label>Zamanlama (İsteğe Bağlı - Sadece Facebook)</label>
            <input type="datetime-local" className="FormControl" bidi={this.scheduleTime} />
        </div>

        <div className="Form-group">
          <Button className="Button Button--primary" loading={this.loading} onclick={this.onsubmit.bind(this)}>
            {this.scheduleTime() ? 'Zamanla (FB)' : 'Paylaş (FB + IG)'}
          </Button>
        </div>
      </div>
    );
  }

  onsubmit(e) {
    e.preventDefault();
    this.loading = true;
    const account = this.accounts[this.selectedAccountIndex()];

    if (!account) { alert('Hesap seçilmedi!'); this.loading = false; return; }

    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/framio/social-share',
      body: {
        message: this.message(),
        imageUrl: this.imageUrl,
        pageId: account.page_id,
        igId: account.ig_id,
        token: account.token,
        scheduleTime: this.scheduleTime()
      }
    }).then((res) => {
      this.loading = false;
      app.modal.close();
      if(res.errors && res.errors.length > 0) {
          app.alerts.show({ type: 'warning' }, 'Kısmi Başarı: ' + res.errors.join(', '));
      } else {
          app.alerts.show({ type: 'success' }, 'İşlem Başarılı!');
      }
    }).catch((err) => {
      this.loading = false;
      app.alerts.show({ type: 'error' }, 'Bir hata oluştu.');
    });
  }
}