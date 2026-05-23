import app from 'flarum/admin/app';

app.initializers.add('framio-social-share', () => {
  app.extensionData
    .for('framio-social-share')
    .registerSetting({
      setting: 'framio-social.page_id',
      label: 'Facebook Sayfa ID (Page ID)',
      type: 'text',
      help: 'Facebook Sayfanızın ID numarası.',
    })
    .registerSetting({
      setting: 'framio-social.instagram_id',
      label: 'Instagram Business ID',
      type: 'text',
      help: 'Facebook sayfasına bağlı Instagram işletme hesabının ID numarası (Genelde 1784... ile başlar).',
    })
    .registerSetting({
      setting: 'framio-social.access_token',
      label: 'Meta Page Access Token',
      type: 'text',
      help: 'Hem Facebook hem Instagram yetkilerine sahip kalıcı token.',
    });
});