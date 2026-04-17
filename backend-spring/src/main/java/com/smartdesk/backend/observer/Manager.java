package com.smartdesk.backend.observer;

import com.smartdesk.backend.model.Desk;

import java.util.ArrayList;
import java.util.List;

public abstract class Manager {

    protected final List<Subscriber> subscribers = new ArrayList<>();

    public void subscribe(Subscriber subscriber) {
        subscribers.add(subscriber);
    }

    public void unsubscribe(Subscriber subscriber) {
        subscribers.remove(subscriber);
    }

    protected void notifySubscribers(Desk context) {
        for (Subscriber subscriber : subscribers) {
            subscriber.update(context);
        }
    }
}
