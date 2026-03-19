const functions = require('firebase-functions');
const admin = require('firebase-admin');

admin.initializeApp();
const db = admin.firestore();

// Health check
exports.health = functions.https.onRequest((req, res) => {
  res.status(200).json({ status: 'healthy', timestamp: Date.now() });
});

// Scheduled: clean up expired coupons (runs daily at midnight)
exports.cleanupExpiredCoupons = functions.pubsub
  .schedule('0 0 * * *')
  .timeZone('Asia/Riyadh')
  .onRun(async () => {
    const now = admin.firestore.Timestamp.now();
    const expiredSnapshot = await db
      .collection('coupons')
      .where('expiresAt', '<', now)
      .where('isActive', '==', true)
      .get();

    const batch = db.batch();
    expiredSnapshot.docs.forEach((doc) => {
      batch.update(doc.ref, { isActive: false, updatedAt: now });
    });

    await batch.commit();
    console.log(`Deactivated ${expiredSnapshot.size} expired coupons`);
  });

// Trigger: increment store coupon count on coupon create
exports.onCouponCreated = functions.firestore
  .document('coupons/{couponId}')
  .onCreate(async (snap) => {
    const coupon = snap.data();
    if (coupon.storeId) {
      await db.collection('stores').doc(coupon.storeId).update({
        couponCount: admin.firestore.FieldValue.increment(1),
      });
    }
  });

// Trigger: decrement store coupon count on coupon delete
exports.onCouponDeleted = functions.firestore
  .document('coupons/{couponId}')
  .onDelete(async (snap) => {
    const coupon = snap.data();
    if (coupon.storeId) {
      await db.collection('stores').doc(coupon.storeId).update({
        couponCount: admin.firestore.FieldValue.increment(-1),
      });
    }
  });

// Trigger: log analytics on coupon usage increment
exports.onCouponUpdated = functions.firestore
  .document('coupons/{couponId}')
  .onUpdate(async (change) => {
    const before = change.before.data();
    const after = change.after.data();

    if (after.usageCount > before.usageCount) {
      await db.collection('analytics').add({
        couponId: change.after.id,
        event: 'coupon_used',
        storeId: after.storeId,
        category: after.category,
        timestamp: admin.firestore.Timestamp.now(),
        platform: 'app',
      });
    }
  });
