<?php

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BlogPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $blog, $user;

    public function __construct($blog, $user)
    {
        $this->blog = $blog;
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject('🎉 Your Blog Has Been Published!')
                    ->view('emails.blog_published')
                    ->with([
                        'user_name' => $this->user->name,
                        'blog_title' => $this->blog->title,
                        'publication_date' => $this->blog->published_at->format('F j, Y'),
                        'blog_tags' => $this->blog->tags->pluck('name')->join(', '),
                        'blog_url' => url('/blogs/' . $this->blog->uuid),
                    ]);
    }
}
