// <oats-jar-stage> — a small, non-interactive three.js turntable used only
// for the landing page hero's overnight-oats jar.
//
// Trimmed from a general-purpose 3D viewer scaffold (three-d-stage,
// bundled with an OBJ/GLB export toolbar and an orbit-drag hint meant for
// a full-viewport model inspector): this embed sits in a small circle
// inside the normal page flow, so none of that applies — a download
// toolbar means nothing to a recipe-app visitor, and draggable orbit
// controls on a small element inside normal scroll flow would fight touch
// scrolling on mobile. What's kept: the renderer, neutral studio
// lighting, camera auto-framed to the model's bounds, resize handling,
// and a continuous slow turntable spin - the animation itself, nothing
// else.
(() => {
  const stylesheet = `
    :host { position: relative; display: block; width: 100%; height: 100%; overflow: hidden; }
    canvas { display: block; outline: none; width: 100%; height: 100%; }
  `;

  class OatsJarStage extends HTMLElement {
    constructor() {
      super();
      const root = this.attachShadow({ mode: 'open' });
      const style = document.createElement('style');
      style.textContent = stylesheet;
      root.appendChild(style);
      this.ready = new Promise((resolve, reject) => {
        this._readyResolve = resolve;
        this._readyReject = reject;
      });
    }

    connectedCallback() {
      if (this._booted) {
        if (this._renderer) {
          this._renderer.setAnimationLoop(this._loop);
          this._ro && this._ro.observe(this);
        }
        return;
      }
      this._booted = true;
      this._boot().catch((err) => this._readyReject(err));
    }

    async _boot() {
      const THREE = await import('three');
      this._THREE = THREE;
      const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
      renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
      this._renderer = renderer;
      this.shadowRoot.appendChild(renderer.domElement);

      const scene = new THREE.Scene();
      this._scene = scene;

      const camera = new THREE.PerspectiveCamera(45, 1, 0.01, 500);
      this._camera = camera;

      scene.add(new THREE.HemisphereLight(0xffffff, 0xd8d2c4, 1.15));
      const key = new THREE.DirectionalLight(0xffffff, 2.2);
      key.position.set(4, 7, 5);
      scene.add(key);
      const fill = new THREE.DirectionalLight(0xfff4e6, 0.55);
      fill.position.set(-5, 3, -4);
      scene.add(fill);

      this._pivot = new THREE.Group();
      scene.add(this._pivot);

      const fit = () => {
        const w = this.clientWidth || 1;
        const h = this.clientHeight || 1;
        renderer.setSize(w, h);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
      };
      fit();
      this._ro = new ResizeObserver(fit);

      this._loop = () => {
        this._pivot.rotation.y += 0.0032;
        renderer.render(scene, camera);
      };
      if (this.isConnected) {
        this._ro.observe(this);
        renderer.setAnimationLoop(this._loop);
      }

      this._readyResolve({ THREE });
    }

    disconnectedCallback() {
      if (this._renderer) this._renderer.setAnimationLoop(null);
      if (this._ro) this._ro.disconnect();
    }

    /** Shows the object, framing the camera to its bounds once. Rotation
     *  happens on a parent pivot group, not the object itself, so the
     *  model's own local transform stays untouched. */
    setObject(object) {
      const THREE = this._THREE;
      if (!THREE) throw new Error('oats-jar-stage: not ready — await stage.ready first');
      if (this._object) this._pivot.remove(this._object);
      this._object = object;
      this._pivot.add(object);

      const box = new THREE.Box3().setFromObject(object);
      if (box.isEmpty()) return;
      const center = box.getCenter(new THREE.Vector3());
      const sphere = box.getBoundingSphere(new THREE.Sphere());
      const dist = (sphere.radius / Math.tan((this._camera.fov * Math.PI) / 360)) * 1.5;
      const dir = new THREE.Vector3(1, 0.5, 1.25).normalize();
      this._camera.position.copy(center).add(dir.multiplyScalar(dist));
      this._camera.lookAt(center);
      this._camera.near = Math.max(dist / 100, 0.01);
      this._camera.far = dist * 100;
      this._camera.updateProjectionMatrix();
    }
  }

  customElements.define('oats-jar-stage', OatsJarStage);
})();
