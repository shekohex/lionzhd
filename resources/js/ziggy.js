const Ziggy = {"url":"http:\/\/localhost","port":null,"defaults":{},"routes":{"home":{"uri":"\/","methods":["GET","HEAD"]},"discover":{"uri":"discover","methods":["GET","HEAD"]},"search":{"uri":"search","methods":["GET","HEAD"]},"search.lightweight":{"uri":"search\/lightweight","methods":["GET","HEAD"]},"movies":{"uri":"movies","methods":["GET","HEAD"]},"movies.show":{"uri":"movies\/{model}","methods":["GET","HEAD"],"wheres":{"model":"[0-9]+"},"parameters":["model"],"bindings":{"model":"num"}},"series":{"uri":"series","methods":["GET","HEAD"]},"series.show":{"uri":"series\/{model}","methods":["GET","HEAD"],"wheres":{"model":"[0-9]+"},"parameters":["model"],"bindings":{"model":"num"}},"profile.edit":{"uri":"settings\/profile","methods":["GET","HEAD"]},"profile.update":{"uri":"settings\/profile","methods":["PATCH"]},"profile.destroy":{"uri":"settings\/profile","methods":["DELETE"]},"password.edit":{"uri":"settings\/password","methods":["GET","HEAD"]},"password.update":{"uri":"settings\/password","methods":["PUT"]},"appearance":{"uri":"settings\/appearance","methods":["GET","HEAD"]},"register":{"uri":"register","methods":["GET","HEAD"]},"login":{"uri":"login","methods":["GET","HEAD"]},"password.request":{"uri":"forgot-password","methods":["GET","HEAD"]},"password.email":{"uri":"forgot-password","methods":["POST"]},"password.reset":{"uri":"reset-password\/{token}","methods":["GET","HEAD"],"parameters":["token"]},"password.store":{"uri":"reset-password","methods":["POST"]},"verification.notice":{"uri":"verify-email","methods":["GET","HEAD"]},"verification.verify":{"uri":"verify-email\/{id}\/{hash}","methods":["GET","HEAD"],"parameters":["id","hash"]},"verification.send":{"uri":"email\/verification-notification","methods":["POST"]},"password.confirm":{"uri":"confirm-password","methods":["GET","HEAD"]},"logout":{"uri":"logout","methods":["POST"]},"storage.local":{"uri":"storage\/{path}","methods":["GET","HEAD"],"wheres":{"path":".*"},"parameters":["path"]}}};
if (typeof window !== 'undefined' && typeof window.Ziggy !== 'undefined') {
  Object.assign(Ziggy.routes, window.Ziggy.routes);
}
export { Ziggy };
